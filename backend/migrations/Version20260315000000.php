<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260315000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create user, map, map_share, and reset_password_request tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE app_user (
            id SERIAL PRIMARY KEY,
            email VARCHAR(180) NOT NULL,
            roles JSON NOT NULL DEFAULT \'[]\',
            password VARCHAR(255) NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT NOW()
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_88BDF3E9E7927C74 ON app_user (email)');
        $this->addSql("COMMENT ON COLUMN app_user.created_at IS '(DC2Type:datetime_immutable)'");

        $this->addSql('CREATE TABLE app_map (
            id SERIAL PRIMARY KEY,
            owner_id INT NOT NULL REFERENCES app_user(id),
            name VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT \'draft\',
            center_lng DOUBLE PRECISION NOT NULL DEFAULT -0.5670392,
            center_lat DOUBLE PRECISION NOT NULL DEFAULT 44.82459,
            zoom DOUBLE PRECISION NOT NULL DEFAULT 12,
            changes JSONB NOT NULL DEFAULT \'{}\'::jsonb,
            splits JSONB NOT NULL DEFAULT \'{}\'::jsonb,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT NOW(),
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT NOW(),
            duplicated_from_id INT DEFAULT NULL REFERENCES app_map(id) ON DELETE SET NULL
        )');
        $this->addSql('CREATE INDEX IDX_MAP_OWNER ON app_map (owner_id)');
        $this->addSql("COMMENT ON COLUMN app_map.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN app_map.updated_at IS '(DC2Type:datetime_immutable)'");

        $this->addSql('CREATE TABLE app_map_share (
            id SERIAL PRIMARY KEY,
            map_id INT NOT NULL REFERENCES app_map(id) ON DELETE CASCADE,
            shared_with_id INT DEFAULT NULL REFERENCES app_user(id) ON DELETE CASCADE,
            token VARCHAR(64) DEFAULT NULL,
            can_edit BOOLEAN NOT NULL DEFAULT FALSE,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT NOW()
        )');
        $this->addSql('CREATE INDEX IDX_SHARE_MAP ON app_map_share (map_id)');
        $this->addSql('CREATE INDEX IDX_SHARE_USER ON app_map_share (shared_with_id)');
        $this->addSql("COMMENT ON COLUMN app_map_share.created_at IS '(DC2Type:datetime_immutable)'");

        $this->addSql('CREATE TABLE app_reset_password_request (
            id SERIAL PRIMARY KEY,
            user_id INT NOT NULL REFERENCES app_user(id) ON DELETE CASCADE,
            selector VARCHAR(20) NOT NULL,
            hashed_token VARCHAR(100) NOT NULL,
            requested_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
        )');
        $this->addSql('CREATE INDEX IDX_RESET_USER ON app_reset_password_request (user_id)');
        $this->addSql("COMMENT ON COLUMN app_reset_password_request.requested_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN app_reset_password_request.expires_at IS '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS app_reset_password_request');
        $this->addSql('DROP TABLE IF EXISTS app_map_share');
        $this->addSql('DROP TABLE IF EXISTS app_map');
        $this->addSql('DROP TABLE IF EXISTS app_user');
    }
}
