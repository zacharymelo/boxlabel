-- Copyright (C) 2026 DPG Supply
--
-- Box Label extrafields table
--

CREATE TABLE llx_box_label_extrafields(
    rowid       INTEGER AUTO_INCREMENT PRIMARY KEY,
    tms         TIMESTAMP,
    fk_object   INTEGER NOT NULL,
    import_key  VARCHAR(14)
) ENGINE=innodb;
