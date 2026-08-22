## Purpose

This wiki is a structured, interlinked knowledge base for a team chat.
Claude maintains the wiki. The human curates sources, asks questions, and guides the analysis.


## Folder structure


```
C:\D\_obs\gdyupin-vm\Project1\raw          -- source documents (immutable -- never modify these)
C:\D\_obs\gdyupin-vm\Project1\wiki         -- markdown pages maintained by Claude
C:\D\_obs\gdyupin-vm\Project1\wiki/index.md -- table of contents for the entire wiki
C:\D\_obs\gdyupin-vm\Project1\wiki/log.md   -- append-only record of all operations
```


## Ingest workflow


When the user adds a new source to `raw/` and asks you to ingest it:

1. Read the full source document
2. Discuss key takeaways with the user before writing anything
3. Create a summary page in `wiki/` named after the source
4. Create or update concept pages for each major idea or entity
5. Add wiki-links ([[page-name]]) to connect related pages
6. Update `wiki/index.md` with new pages and one-line descriptions
7. Append an entry to `wiki/log.md` with the date, source name, and what changed

A single source may touch 10-15 wiki pages. That is normal.

## Page format

Every wiki page should follow this structure:

```markdown
# Page Title


**Summary**: One to two sentences describing this page.


**Sources**: List of raw source files this page draws from.


**Last updated**: Date of most recent update.


---


Main content goes here. Use clear headings and short paragraphs.


Link to related concepts using [[wiki-links]] throughout the text.


## Related pages


- [[related-concept-1]]
- [[related-concept-2]]
```


## Citation rules


- Every factual claim should reference its source file
- Use the format (source: filename.pdf) after the claim
- If two sources disagree, note the contradiction explicitly
- If a claim has no source, mark it as needing verification


## Question answering

When the user asks a question:


1. Read `wiki/index.md` first to find relevant pages
2. Read those pages and synthesize an answer
3. Cite specific wiki pages in your response
4. If the answer is not in the wiki, say so clearly
5. If the answer is valuable, offer to save it as a new wiki page

Good answers should be filed back into the wiki so they compound over time.


## Lint

When the user asks you to lint or audit the wiki:
- Check for contradictions between pages
- Find orphan pages (no inbound links from other pages)
- Identify concepts mentioned in pages that lack their own page
- Flag claims that may be outdated based on newer sources
- Check that all pages follow the page format above
- Report findings as a numbered list with suggested fixes


## Rules

- Never modify anything in the `raw/` folder
- Always update `wiki/index.md` and `wiki/log.md` after changes
- Keep page names lowercase with hyphens (e.g. `machine-learning.md`)
- Write in clear, plain language
- When uncertain about how to categorize something, ask the user

## Git delivery

- Every completed, verified atomic commit is pushed immediately to remote `ditacacsgui` on the current tracking branch (normally `master`). Local-only commits are not acceptable for finished work.
- Unfinished or unverified changes stay unpushed until they are verified. This prevents publishing known-broken work while still delivering every verified commit right away.
- Before any push, inspect `git status`, `git diff`, the staged diff, and the recent log. Never push secrets, `.omo/` evidence, caches (`__pycache__/`, `.php-cs-fixer.cache`), runtime DB/session/cache files, generated credentials, or unrelated untracked artifacts.
- Normal push only: `git push ditacacsgui master`. Force-push, history rewrite (amend/reset/rebase/squash of pushed work), global Git config changes, and skipped hooks are forbidden unless the user explicitly authorizes them.
- A failed push is a blocking delivery failure: stop and report the exact error, including the failed range and remote.
- Verify after push: local HEAD must equal the remote tracking ref and `git ls-remote ditacacsgui refs/heads/master`.
