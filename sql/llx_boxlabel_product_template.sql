-- Copyright (C) 2026 DPG Supply
--
-- Per-product label template — which fields to show on box labels
--

CREATE TABLE llx_boxlabel_product_template(
    rowid           INTEGER         AUTO_INCREMENT PRIMARY KEY,
    fk_product      INTEGER         NOT NULL,
    entity          INTEGER         NOT NULL DEFAULT 1,
    enabled_fields  TEXT,
    date_creation   DATETIME        NOT NULL,
    tms             TIMESTAMP,
    fk_user_creat   INTEGER,
    import_key      VARCHAR(14)
) ENGINE=innodb;
