-- Copyright (C) 2026 DPG Supply
--
-- Box Label extrafields indexes
--

ALTER TABLE llx_box_label_extrafields ADD INDEX idx_box_label_extrafields_fk_object (fk_object);
