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
- Implement option 1 from the last prompt.
- Seeing this error in the publishes at column: Warning: Undefined array key "publish_at" in /app/public/admin.php on line 96
- Check current the time in eastern standard time for publish_at on access and make document available if at or past available time.
- Add test for publish_at when the document becomes available.
- Update UI to display time for publish at is in EST. Low effort
- What does datetime-local type use? Low effort
- Is there another option other than datetime-local?
- Implement option 4
- For publish_at, convert all times are in eastern standard time
- Parse error: syntax error, unexpected token "->" in /app/public/view.php on line 30
- When document is not available yet, display in local time when it will be available.
- Update tests with UTC time change
- Update Prompts.md with prompts used this session. Low effort.
