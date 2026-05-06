ALTER TABLE documents ADD COLUMN slug TEXT;
CREATE UNIQUE INDEX idx_documents_slug ON documents (slug);
CREATE TRIGGER documents_require_slug
BEFORE INSERT ON documents
FOR EACH ROW
WHEN NEW.slug IS NULL OR NEW.slug = ''
BEGIN
    SELECT RAISE(ABORT, 'slug is required');
END;
