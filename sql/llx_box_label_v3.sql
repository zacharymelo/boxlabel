-- Copyright (C) 2026 DPG Supply
--
-- Migration v3: Add free_text fields for configurable label text
--

ALTER TABLE llx_box_label ADD free_text TEXT AFTER product_description;
ALTER TABLE llx_boxlabel_product_template ADD free_text_default TEXT AFTER enabled_fields;
