-- Copyright (C) 2026 DPG Supply
--
-- Box Label table
--

CREATE TABLE llx_box_label(
    rowid               INTEGER         AUTO_INCREMENT PRIMARY KEY,
    ref                 VARCHAR(30)     NOT NULL,
    entity              INTEGER         NOT NULL DEFAULT 1,
    fk_product          INTEGER         NOT NULL,
    fk_mo               INTEGER,
    fk_product_lot      INTEGER,
    batch               VARCHAR(128),
    serial_number       VARCHAR(128),
    product_label       VARCHAR(255),
    product_description TEXT,
    date_manufactured   DATETIME,
    qty_labels          INTEGER         NOT NULL DEFAULT 1,
    status              SMALLINT        NOT NULL DEFAULT 0,
    note_private        TEXT,
    note_public         TEXT,
    date_creation       DATETIME        NOT NULL,
    tms                 TIMESTAMP,
    fk_user_creat       INTEGER         NOT NULL,
    fk_user_modif       INTEGER,
    import_key          VARCHAR(14),
    model_pdf           VARCHAR(255),
    last_main_doc       VARCHAR(255)
) ENGINE=innodb;
