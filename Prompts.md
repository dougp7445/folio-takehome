# Session Prompts

## Schedule Publishing

- Change default effort level to medium
- Run tests after every prompt that results in a code change
- Write each prompt to a Prompts.md file.
    - Not working not worth effort for 3 hour test can just prompt manually at end.
- Give an explanation of schema.sql. Low effort
- Do not make edits to schema.sql. Warn if a change happens.
- Write prompts to Prompts.md file after every prompt has completed. Low effort.
- Give ideas for a migration plan for allowing users to select a time and date that a document will become available once shared. Use low effort.
    1. Add publish_at column to documents — nullable datetime; NULL means immediately available. view.php checks publish_at <= now() before showing content, otherwise returns a "not yet available" message. Simplest, fewest moving parts.
	2. Add publish_at to shares instead — lets different share links for the same document have different publish times. More flexible but more complex, and probably not what customers want.
	3. Separate status + publish_at — add both a status enum (draft/published) and a publish_at datetime. Gives staff an explicit publish workflow on top of scheduling. More UI surface, probably overkill here.
- Implement option 1 from the last prompt.
- Seeing this error in the publishes at column: Warning: Undefined array key "publish_at" in /app/public/admin.php on line 96
- Check current the time in eastern standard time for publish_at on access and make document available if at or past available time.
- Add test for publish_at when the document becomes available.
- Update UI to display time for publish at is in EST. Low effort
- What does datetime-local type use? Low effort
- Is there another option other than datetime-local?
	1. Two separate inputs — <input type="date"> + <input type="time"> — same timezone problem, just split across two fields.
	2. Plain text input — <input type="text"> with a placeholder like 2026-05-06 14:30 — staff types it manually, you parse it server-side. More explicit but error-prone.
	3. A JS datetime picker library (e.g. Flatpickr) — lets you configure timezone display, add an EST label, restrict formats. Still sends no timezone to the server, but you control the UX more precisely.
	4. Store and display in UTC, convert on the way in/out — use a hidden field with the UTC equivalent after a JS conversion. Correct but adds complexity.
- Implement option 4
- For publish_at, convert all times are in eastern standard time
- Parse error: syntax error, unexpected token "->" in /app/public/view.php on line 30
- When document is not available yet, display in local time when it will be available.
- Update tests with UTC time change
- Update Prompts.md with prompts used this session. Low effort.

## Human-Readable Document IDs (Slugs)

- Give me a migration plan for adding short, human readable ids as an option for publishing a document.
    1. Slug from title + short random suffix — e.g. welcome-packet-4k2x. Derive from title at creation time (lowercase, hyphenate, strip special chars), append a 4-char random alphanumeric to avoid collisions. Stored in a slug column with a unique index. Natural, readable, memorable.
			- Best option for user interaction as this is its intended purpose.  Title means something and slug can be treated as the unique document.
	2. Adjective-noun-number — e.g. swift-river-42. Fully generated, never derived from content. No collision with title changes. Less descriptive but always safe and speakable.
	3. Prefixed random ID — e.g. FOLIO-7QX4. Short, brand-consistent, easy to say aloud. Not human-meaningful but recognizable as a Folio ID. Simple to generate and guaranteed collision-resistant at scale.
	4. Sequential slug — e.g. welcome-packet, welcome-packet-2. Derived from title, no random suffix. Cleanest URLs but requires a uniqueness check loop and breaks if titles change.
- Slug appear for both staff and recipients. Slugs cannot be edited after creation. Complements existing share link.
    - This is from what Claude presented as angles to consider for this change.
- Implement option 1 for the migration.
- app-1 | Fatal error: Uncaught PDOException: SQLSTATE[HY000]: General error: 1 Cannot add a UNIQUE column in /app/seed.php:14
- Do not skip slug generation in publish_at tests
- Do not allow new document creation without a slug
- Update Prompts.md with prompts used this session. Low effort.

## Search by Title

- Give me some options for a user searching by the title of the document with upsides and downsides. Take search time into consideration. Low effort.
  1. Exact match
		WHERE title = ?
		Fast (uses index if one exists), zero ambiguity
		Useless in practice — staff must remember the exact title including capitalization

	2. Prefix match
		WHERE title LIKE 'onboarding%'
		Fast with an index on title, natural to type
		Misses mid-word matches ("packet" won't find "Welcome Packet")

	3. Substring match
		WHERE title LIKE '%onboarding%'

		Flexible, finds partial matches anywhere in the title
		Full table scan every time — SQLite can't use a B-tree index for leading wildcards. Fine at small scale (dozens of docs), degrades linearly as the table grows

	4. SQLite FTS5 (full-text search)
		CREATE VIRTUAL TABLE documents_fts USING fts5(title) + WHERE documents_fts MATCH ?
		Fast even on large datasets (inverted index), supports stemming/prefix natively
		Extra table to maintain, more migration complexity, slight overkill for an internal tool with low document counts

	5. Application-side fuzzy match
		Load all titles into PHP, score them with something like Levenshtein distance
		Tolerates typos ("onbording" finds "Onboarding")
		Loads entire title list into memory on every search; only viable if the document count stays small

- Can option 4 and option 5 be combined? Low effort
- Update Prompts.md with prompts. Low effort.
- Add tests for searching that include partial titles and mispelled searchs.
- Would indexing the database help with being to implement Levenshtein?
