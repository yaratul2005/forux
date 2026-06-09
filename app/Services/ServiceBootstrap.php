<?php

namespace App\Services;

use Core\Container;
use App\Services\Mail\MailServiceInterface;
use App\Services\Mail\LocalMailService;
use App\Services\Mail\SmtpMailService;
use App\Services\Mail\SendGridMailService;

use App\Services\Storage\StorageServiceInterface;
use App\Services\Storage\LocalStorageService;
use App\Services\Storage\S3StorageService;

use App\Services\Search\SearchServiceInterface;
use App\Services\Search\DbSearchService;
use App\Services\Search\MeilisearchService;

use App\Services\OAuth\OAuthServiceInterface;
use App\Services\OAuth\NullOAuthService;
use App\Services\OAuth\OAuthService;

use App\Services\Moderation\SpamFilterInterface;
use App\Services\Moderation\LocalSpamFilter;
use App\Services\Moderation\AiModerationFilter;

/**
 * Service Bootstrap Resolver.
 * Decrypts active credentials from the vault and registers service interface bindings in the DI container.
 */
class ServiceBootstrap
{
    /**
     * Register dynamic services into the Container.
     *
     * @param Container $container
     */
    public static function register(Container $container): void
    {
        // 1. Register the EncryptionService first
        if (!$container->has(EncryptionService::class)) {
            $container->singleton(EncryptionService::class, function ($c) {
                return new EncryptionService($c->get('config'));
            });
        }

        // 2. Load and decrypt all service credentials from vault
        $vault = self::loadVault($container);

        // 3. Register Mail Service
        $container->singleton(MailServiceInterface::class, function ($c) use ($vault) {
            if (isset($vault['SendGrid']['ACTIVE'])) {
                return new SendGridMailService($vault['SendGrid'], $c->get('config'));
            } elseif (isset($vault['SMTP']['ACTIVE'])) {
                return new SmtpMailService($vault['SMTP'], $c->get('config'));
            }
            return new LocalMailService();
        });

        // 4. Register Storage Service
        $container->singleton(StorageServiceInterface::class, function ($c) use ($vault) {
            if (isset($vault['S3Storage']['ACTIVE'])) {
                return new S3StorageService($vault['S3Storage']);
            }
            return new LocalStorageService($c->get('config'));
        });

        // 5. Register Search Service
        $container->singleton(SearchServiceInterface::class, function ($c) use ($vault) {
            if (isset($vault['Meilisearch']['ACTIVE'])) {
                return new MeilisearchService($vault['Meilisearch']);
            }
            return new DbSearchService($c->get(\PDO::class));
        });

        // 6. Register OAuth Service
        $container->singleton(OAuthServiceInterface::class, function ($c) use ($vault) {
            $hasActiveOauth = false;
            $oauthCreds = [];

            foreach (['GoogleOAuth', 'GitHubOAuth', 'DiscordOAuth'] as $serviceName) {
                if (isset($vault[$serviceName])) {
                    if (isset($vault[$serviceName]['ACTIVE'])) {
                        $hasActiveOauth = true;
                    }
                    $oauthCreds = array_merge($oauthCreds, $vault[$serviceName]);
                }
            }

            if ($hasActiveOauth) {
                return new OAuthService($c->get('config'), $oauthCreds);
            }
            return new NullOAuthService();
        });

        // 7. Register AI Moderation Spam Filter
        $container->singleton(SpamFilterInterface::class, function ($c) use ($vault) {
            if (isset($vault['AiModeration']['ACTIVE'])) {
                return new AiModerationFilter($vault['AiModeration']);
            }
            return new LocalSpamFilter($c->get(\Core\Settings::class));
        });
    }

    /**
     * Fetch all credentials from database, decrypt them, and group them by service name.
     */
    protected static function loadVault(Container $container): array
    {
        $vault = [];
        if (!$container->has(\PDO::class)) {
            return $vault;
        }

        try {
            $pdo = $container->get(\PDO::class);

            // Double check table existence before querying
            $tableCheck = $pdo->query("SHOW TABLES LIKE 'service_credentials'");
            if ($tableCheck->rowCount() === 0) {
                return $vault;
            }

            $stmt = $pdo->query("SELECT service_name, credential_key, credential_value, is_active FROM service_credentials");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($rows)) {
                return $vault;
            }

            $encryptionService = $container->get(EncryptionService::class);

            foreach ($rows as $row) {
                $service = $row['service_name'];
                $key = $row['credential_key'];
                $encryptedValue = $row['credential_value'];
                $isActive = (int)$row['is_active'];

                $decryptedValue = '';
                if (!empty($encryptedValue)) {
                    try {
                        $decryptedValue = $encryptionService->decrypt($encryptedValue);
                    } catch (\Throwable $e) {
                        $decryptedValue = ''; // Fallback for invalid/uncorrupted payload
                    }
                }

                if (!isset($vault[$service])) {
                    $vault[$service] = [];
                }

                $vault[$service][$key] = $decryptedValue;

                // Mark the service as active if any of its keys is marked active
                if ($isActive === 1) {
                    $vault[$service]['ACTIVE'] = true;
                }
            }
        } catch (\Throwable $e) {
            // Fail silently to prevent site crash if DB configuration is bad
        }

        return $vault;
    }
}
