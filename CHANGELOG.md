# Changelog

## [1.8.0] - 2026-04-04

### Added
- Print note_public on label PDF when present (italic, below product description)
- Note field on create and edit forms with placeholder help text
- Display note_public on label card view

## [1.7.1] - 2026-04-02

### Reverted
- Remove free_text feature, use product extrafields instead

## [1.7.0] - 2026-04-02

### Fixed
- Template save fails when free_text_default column doesn't exist yet

## [1.6.1] - 2026-04-02

### Fixed
- Prefix 11 generic lang keys with BoxLabel
- Fix phpcs violations

## [1.6.0] - 2026-03-31

### Added
- Auto-archive shipped labels with retention delete cron

### Fixed
- Use NOSCANPOSTFORINJECTION to whitelist header text fields

## [1.5.1] - 2026-03-31

### Fixed
- Grey out Generate button when all serials already have labels
- Tab badge — correct class name in tab def and accept 2nd arg in countForMo

## [1.5.0] - 2026-03-31

### Added
- Configurable label header — title, subtitle, logo via admin setup

## [1.4.1] - 2026-03-31

### Fixed
- Add badge count on MO Box Labels tab

## [1.4.0] - 2026-03-31

### Added
- Print All Labels — combined multi-page PDF from MO tab

## [1.3.3] - 2026-03-30

### Fixed
- MO tab objecttype must be 'mo@mrp' not 'mo'

## [1.3.2] - 2026-03-30

### Fixed
- Auto-generate PDFs on MO label creation
- Add Regenerate All button

## [1.3.0] - 2026-03-30

### Added
- Cascading FK dropdowns, auto-generation, per-product templates, redesigned label

## [1.0.1] - 2026-03-30

### Added
- Initial boxlabel module
