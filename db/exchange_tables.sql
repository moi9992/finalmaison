-- =============================================
-- Tables pour le système d'échange d'objets
-- À exécuter dans phpMyAdmin sur projet_d2jsp
-- =============================================

USE projet_d2jsp;

-- ---------------------------------------------
-- Table : items (inventaire d'objets)
-- ---------------------------------------------
CREATE TABLE IF NOT EXISTS items (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_id   INT UNSIGNED NOT NULL,
    name       VARCHAR(100) NOT NULL,
    icon       VARCHAR(10)  NOT NULL DEFAULT '📦',
    rarity     ENUM('commun','rare','épique','légendaire') NOT NULL DEFAULT 'commun',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------
-- Table : item_trades (échanges d'objets)
-- ---------------------------------------------
CREATE TABLE IF NOT EXISTS item_trades (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    from_user_id     INT UNSIGNED NOT NULL,
    to_user_id       INT UNSIGNED NOT NULL,
    offer_item_ids   JSON         NOT NULL,
    request_item_ids JSON         NOT NULL,
    status           ENUM('en_attente','accepté','refusé') NOT NULL DEFAULT 'en_attente',
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (to_user_id)   REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
