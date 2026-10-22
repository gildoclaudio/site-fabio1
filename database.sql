-- Base de dados do site Fábio Lourenço
-- Importar uma única vez (phpMyAdmin → Importar), na base de dados criada na OVHcloud.

CREATE TABLE IF NOT EXISTS pedidos (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  criado_em     DATETIME NOT NULL,
  nome          VARCHAR(120) NOT NULL,
  telefone      VARCHAR(30)  NOT NULL,
  email         VARCHAR(160) NULL,
  tipo_imovel   VARCHAR(60)  NOT NULL,
  concelho      VARCHAR(40)  NOT NULL,
  freguesia     VARCHAR(100) NULL,
  area_m2       INT UNSIGNED NULL,
  objetivo      VARCHAR(60)  NOT NULL,
  prazo         VARCHAR(80)  NOT NULL,
  consentimento TINYINT(1)   NOT NULL DEFAULT 0,
  ip_hash       CHAR(64)     NULL,
  contactado    TINYINT(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_criado (criado_em),
  KEY idx_ip (ip_hash, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
