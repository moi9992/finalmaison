-- =============================================
-- Base de données : projet_d2jsp
-- =============================================

CREATE DATABASE IF NOT EXISTS projet_d2jsp
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE projet_d2jsp;

-- ---------------------------------------------
-- Table : users
-- ---------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(30)  NOT NULL UNIQUE,
    email         VARCHAR(100) NOT NULL UNIQUE,
    password      VARCHAR(255) NOT NULL,
    avatar        VARCHAR(255) DEFAULT NULL,
    bio           TEXT         DEFAULT NULL,
    forum_gold    INT UNSIGNED NOT NULL DEFAULT 0,
    reputation    INT          NOT NULL DEFAULT 0,
    role          ENUM('user','moderator','admin') NOT NULL DEFAULT 'user',
    is_banned     TINYINT(1)   NOT NULL DEFAULT 0,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login    DATETIME     DEFAULT NULL
) ENGINE=InnoDB;

-- ---------------------------------------------
-- Table : forum_categories
-- ---------------------------------------------
CREATE TABLE IF NOT EXISTS forum_categories (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100) NOT NULL,
    description   TEXT         DEFAULT NULL,
    slug          VARCHAR(100) NOT NULL UNIQUE,
    sort_order    INT UNSIGNED NOT NULL DEFAULT 0,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------
-- Table : forum_topics
-- ---------------------------------------------
CREATE TABLE IF NOT EXISTS forum_topics (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id   INT UNSIGNED NOT NULL,
    user_id       INT UNSIGNED NOT NULL,
    title         VARCHAR(200) NOT NULL,
    views         INT UNSIGNED NOT NULL DEFAULT 0,
    is_pinned     TINYINT(1)   NOT NULL DEFAULT 0,
    is_locked     TINYINT(1)   NOT NULL DEFAULT 0,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES forum_categories(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)     REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------
-- Table : forum_posts
-- ---------------------------------------------
CREATE TABLE IF NOT EXISTS forum_posts (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    topic_id      INT UNSIGNED NOT NULL,
    user_id       INT UNSIGNED NOT NULL,
    content       TEXT         NOT NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (topic_id) REFERENCES forum_topics(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)  REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------
-- Table : trades (annonces de trading)
-- ---------------------------------------------
CREATE TABLE IF NOT EXISTS trades (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED NOT NULL,
    type          ENUM('sell','buy') NOT NULL,
    game          VARCHAR(100) NOT NULL,
    title         VARCHAR(200) NOT NULL,
    description   TEXT         DEFAULT NULL,
    price_fg      INT UNSIGNED NOT NULL DEFAULT 0,
    status        ENUM('open','closed','traded') NOT NULL DEFAULT 'open',
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------
-- Table : trade_offers (offres sur les annonces)
-- ---------------------------------------------
CREATE TABLE IF NOT EXISTS trade_offers (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    trade_id      INT UNSIGNED NOT NULL,
    user_id       INT UNSIGNED NOT NULL,
    amount_fg     INT UNSIGNED NOT NULL DEFAULT 0,
    message       TEXT         DEFAULT NULL,
    status        ENUM('pending','accepted','declined') NOT NULL DEFAULT 'pending',
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (trade_id) REFERENCES trades(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)  REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------
-- Table : transactions (mouvements de Forum Gold)
-- ---------------------------------------------
CREATE TABLE IF NOT EXISTS transactions (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    from_user_id  INT UNSIGNED DEFAULT NULL,
    to_user_id    INT UNSIGNED NOT NULL,
    amount        INT UNSIGNED NOT NULL,
    reason        VARCHAR(255) NOT NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (to_user_id)   REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------
-- Table : reputation (feedback après échange)
-- ---------------------------------------------
CREATE TABLE IF NOT EXISTS reputation (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    from_user_id  INT UNSIGNED NOT NULL,
    to_user_id    INT UNSIGNED NOT NULL,
    trade_id      INT UNSIGNED DEFAULT NULL,
    rating        ENUM('positive','neutral','negative') NOT NULL,
    comment       TEXT         DEFAULT NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (to_user_id)   REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (trade_id)     REFERENCES trades(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------
-- Table : messages (messagerie privée)
-- ---------------------------------------------
CREATE TABLE IF NOT EXISTS messages (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    from_user_id  INT UNSIGNED NOT NULL,
    to_user_id    INT UNSIGNED NOT NULL,
    subject       VARCHAR(200) NOT NULL,
    content       TEXT         NOT NULL,
    is_read       TINYINT(1)   NOT NULL DEFAULT 0,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (to_user_id)   REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------
-- Table : admin_logs
-- ---------------------------------------------
CREATE TABLE IF NOT EXISTS admin_logs (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id      INT UNSIGNED NOT NULL,
    action        VARCHAR(255) NOT NULL,
    target        VARCHAR(255) DEFAULT NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------
-- Table : notifications
-- ---------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED NOT NULL,
    from_user_id  INT UNSIGNED NOT NULL,
    type          ENUM('topic_reply','trade_offer') NOT NULL,
    reference_id  INT UNSIGNED NOT NULL,
    message       VARCHAR(255) NOT NULL,
    is_read       TINYINT(1)   NOT NULL DEFAULT 0,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)      REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------
-- Données de base : catégories du forum
-- ---------------------------------------------
INSERT INTO forum_categories (name, description, slug, sort_order) VALUES
('Dofus',              'Trading et discussion sur Dofus',                   'dofus',               1),
('League of Legends',  'Trading et discussion sur League of Legends',       'league-of-legends',   2),
('TFT',                'Trading et discussion sur Teamfight Tactics',       'tft',                 3),
('Steam',              'Échanges et deals sur Steam',                       'steam',               4),
('Valorant',           'Trading et discussion sur Valorant',                'valorant',            5),
('Minecraft',          'Trading et discussion sur Minecraft',               'minecraft',           6),
('Général',            'Discussion générale sur les jeux et le trading',    'general',             7),
('Support',            'Aide et support pour les utilisateurs',             'support',             8);
