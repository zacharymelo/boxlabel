-- Copyright (C) 2026 DPG Supply
--
-- Per-product auto-label generation settings
--

CREATE TABLE llx_boxlabel_product_auto(
    rowid           INTEGER         AUTO_INCREMENT PRIMARY KEY,
    fk_product      INTEGER         NOT NULL,
    entity          INTEGER         NOT NULL DEFAULT 1,
    auto_label      SMALLINT        NOT NULL DEFAULT 1,
    date_creation   DATETIME        NOT NULL,
    tms             TIMESTAMP,
    fk_user_creat   INTEGER,
    import_key      VARCHAR(14)
) ENGINE=innodb;
