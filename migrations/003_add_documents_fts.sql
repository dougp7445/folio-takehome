CREATE VIRTUAL TABLE documents_fts USING fts5(title, content='documents', content_rowid='id');

INSERT INTO documents_fts(rowid, title) SELECT id, title FROM documents;

CREATE TRIGGER documents_fts_insert AFTER INSERT ON documents BEGIN
    INSERT INTO documents_fts(rowid, title) VALUES (new.id, new.title);
END;

CREATE TRIGGER documents_fts_update AFTER UPDATE ON documents BEGIN
    INSERT INTO documents_fts(documents_fts, rowid, title) VALUES ('delete', old.id, old.title);
    INSERT INTO documents_fts(rowid, title) VALUES (new.id, new.title);
END;

CREATE TRIGGER documents_fts_delete BEFORE DELETE ON documents BEGIN
    INSERT INTO documents_fts(documents_fts, rowid, title) VALUES ('delete', old.id, old.title);
END;
