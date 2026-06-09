<?php

namespace Modules\Messaging\Controllers;

use Core\Response;
use Core\Request;
use Core\View;
use Modules\Auth\Services\AuthService;
use PDO;
use Exception;
use Throwable;

/**
 * Controller to handle user private messages
 */
class MessagingController
{
    protected AuthService $auth;
    protected PDO $pdo;
    protected Request $request;

    /**
     * Create a new MessagingController instance.
     */
    public function __construct(AuthService $auth, PDO $pdo, Request $request)
    {
        $this->auth = $auth;
        $this->pdo = $pdo;
        $this->request = $request;
    }

    /**
     * Private helper to check user auth.
     */
    protected function requireAuth(): ?Response
    {
        if (!$this->auth->check()) {
            return Response::redirect('/login');
        }
        return null;
    }

    /**
     * Display inbox list of conversations.
     */
    public function inbox(): Response
    {
        if ($redirect = $this->requireAuth()) {
            return $redirect;
        }

        $user = $this->auth->user();

        try {
            $stmt = $this->pdo->prepare("
                SELECT c.*, 
                       (SELECT GROUP_CONCAT(u.username) FROM private_conversation_participants p2 
                        JOIN users u ON p2.user_id = u.id 
                        WHERE p2.conversation_id = c.id AND p2.user_id != ?) as recipient_names,
                       last_m.body as last_message_body,
                       last_m.created_at as last_message_at
                FROM private_conversations c
                JOIN private_conversation_participants p ON c.id = p.conversation_id
                LEFT JOIN private_messages last_m ON last_m.id = (
                    SELECT MAX(id) FROM private_messages WHERE conversation_id = c.id
                )
                WHERE p.user_id = ?
                ORDER BY COALESCE(last_message_at, c.created_at) DESC
            ");
            $stmt->execute([$user['id'], $user['id']]);
            $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            $conversations = [];
        }

        return View::render('conversations', [
            'conversations' => $conversations,
            'title' => 'Inbox - Private Messages'
        ]);
    }

    /**
     * Show form to start a new private message thread.
     */
    public function newConversationForm(?string $error = null): Response
    {
        if ($redirect = $this->requireAuth()) {
            return $redirect;
        }

        $to = $this->request->input('to', '');

        return View::render('new_conversation', [
            'to' => $to,
            'error' => $error,
            'title' => 'New Message - Forux'
        ]);
    }

    /**
     * Process creation of a new private conversation.
     */
    public function createConversation(): Response
    {
        if ($redirect = $this->requireAuth()) {
            return $redirect;
        }

        $currentUser = $this->auth->user();
        $recipientName = trim($this->request->input('recipient', ''));
        $body = trim($this->request->input('body', ''));

        if (empty($recipientName) || empty($body)) {
            return $this->newConversationForm('All fields are required.');
        }

        try {
            // Find recipient
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE username = ? AND deleted_at IS NULL");
            $stmt->execute([$recipientName]);
            $recipientId = $stmt->fetchColumn();

            if (!$recipientId) {
                return $this->newConversationForm("User @{$recipientName} does not exist.");
            }

            if ((int)$recipientId === (int)$currentUser['id']) {
                return $this->newConversationForm("You cannot start a conversation with yourself.");
            }

            // Check if blocking exists
            $stmtBlock = $this->pdo->prepare("
                SELECT COUNT(*) FROM user_blocks 
                WHERE (user_id = ? AND blocked_user_id = ?) 
                   OR (user_id = ? AND blocked_user_id = ?)
            ");
            $stmtBlock->execute([$currentUser['id'], $recipientId, $recipientId, $currentUser['id']]);
            if ((int)$stmtBlock->fetchColumn() > 0) {
                return $this->newConversationForm("Unable to start conversation. You have blocked this user or they have blocked you.");
            }

            // Create conversation in transaction
            $this->pdo->beginTransaction();

            $this->pdo->exec("INSERT INTO private_conversations (title) VALUES (NULL)");
            $convId = $this->pdo->lastInsertId();

            // Insert participants
            $stmtPart = $this->pdo->prepare("INSERT INTO private_conversation_participants (conversation_id, user_id, last_read_at) VALUES (?, ?, CURRENT_TIMESTAMP)");
            $stmtPart->execute([$convId, $currentUser['id']]);
            $stmtPart->execute([$convId, $recipientId]);

            // Insert message
            $stmtMsg = $this->pdo->prepare("INSERT INTO private_messages (conversation_id, sender_id, body) VALUES (?, ?, ?)");
            $stmtMsg->execute([$convId, $currentUser['id'], $body]);

            $this->pdo->commit();

            return Response::redirect("/messages/{$convId}");

        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return $this->newConversationForm($e->getMessage());
        }
    }

    /**
     * Show private conversation history.
     */
    public function showConversation(int $id, ?string $error = null): Response
    {
        if ($redirect = $this->requireAuth()) {
            return $redirect;
        }

        $user = $this->auth->user();

        try {
            // Check if participant
            $stmtCheck = $this->pdo->prepare("SELECT COUNT(*) FROM private_conversation_participants WHERE conversation_id = ? AND user_id = ?");
            $stmtCheck->execute([$id, $user['id']]);
            if ((int)$stmtCheck->fetchColumn() === 0) {
                return View::render('error', [
                    'title' => 'Forbidden',
                    'message' => 'You are not a participant in this conversation.'
                ], 403);
            }

            // Mark as read
            $stmtRead = $this->pdo->prepare("UPDATE private_conversation_participants SET last_read_at = CURRENT_TIMESTAMP WHERE conversation_id = ? AND user_id = ?");
            $stmtRead->execute([$id, $user['id']]);

            // Fetch recipient name
            $stmtName = $this->pdo->prepare("
                SELECT u.username FROM private_conversation_participants p
                JOIN users u ON p.user_id = u.id
                WHERE p.conversation_id = ? AND p.user_id != ?
            ");
            $stmtName->execute([$id, $user['id']]);
            $recipientName = $stmtName->fetchColumn() ?: 'Group Chat';

            // Fetch messages
            $stmtMsg = $this->pdo->prepare("
                SELECT pm.*, u.username as sender_name, u.avatar_url as sender_avatar, u.email as sender_email 
                FROM private_messages pm
                JOIN users u ON pm.sender_id = u.id
                WHERE pm.conversation_id = ?
                ORDER BY pm.id ASC
            ");
            $stmtMsg->execute([$id]);
            $messages = $stmtMsg->fetchAll(PDO::FETCH_ASSOC) ?: [];

        } catch (Throwable $e) {
            return View::render('error', [
                'title' => 'Database Error',
                'message' => $e->getMessage()
            ], 500);
        }

        return View::render('conversation', [
            'conversationId' => $id,
            'recipientName' => $recipientName,
            'messages' => $messages,
            'error' => $error,
            'title' => "Chat with @{$recipientName} - Forux"
        ]);
    }

    /**
     * Send a reply message.
     */
    public function reply(int $id): Response
    {
        if ($redirect = $this->requireAuth()) {
            return $redirect;
        }

        $user = $this->auth->user();
        $body = trim($this->request->input('body', ''));

        if (empty($body)) {
            return $this->showConversation($id, 'Message body cannot be empty.');
        }

        try {
            // Check if participant
            $stmtCheck = $this->pdo->prepare("SELECT COUNT(*) FROM private_conversation_participants WHERE conversation_id = ? AND user_id = ?");
            $stmtCheck->execute([$id, $user['id']]);
            if ((int)$stmtCheck->fetchColumn() === 0) {
                throw new Exception('Forbidden. Access denied.');
            }

            // Get recipient ID to check block list
            $stmtRecipient = $this->pdo->prepare("SELECT user_id FROM private_conversation_participants WHERE conversation_id = ? AND user_id != ?");
            $stmtRecipient->execute([$id, $user['id']]);
            $recipientId = $stmtRecipient->fetchColumn();

            if ($recipientId) {
                // Check blocks
                $stmtBlock = $this->pdo->prepare("
                    SELECT COUNT(*) FROM user_blocks 
                    WHERE (user_id = ? AND blocked_user_id = ?) 
                       OR (user_id = ? AND blocked_user_id = ?)
                ");
                $stmtBlock->execute([$user['id'], $recipientId, $recipientId, $user['id']]);
                if ((int)$stmtBlock->fetchColumn() > 0) {
                    throw new Exception("Unable to send reply. A block exists between you and this user.");
                }
            }

            $stmt = $this->pdo->prepare("INSERT INTO private_messages (conversation_id, sender_id, body) VALUES (?, ?, ?)");
            $stmt->execute([$id, $user['id'], $body]);

            // Update conversation timestamp
            $this->pdo->prepare("UPDATE private_conversations SET updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$id]);

            return Response::redirect("/messages/{$id}");

        } catch (Throwable $e) {
            return $this->showConversation($id, $e->getMessage());
        }
    }
}
