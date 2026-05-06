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
