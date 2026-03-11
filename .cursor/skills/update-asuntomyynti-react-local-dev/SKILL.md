---
name: update-asuntomyynti-react-local-dev
description: Wrapper skill that delegates to the version-controlled skill under .agents/skills.
---

# Wrapper: Update Asuntomyynti React (Local Dev)

You are a Cursor agent. The canonical, version-controlled instructions for this skill
live in `.agents/skills/update-asuntomyynti-react-local-dev/SKILL.md`.

## What to do

1. Read the full skill file from:
   - `drupal-asuntotuotanto/.agents/skills/update-asuntomyynti-react-local-dev/SKILL.md`
2. Follow its instructions exactly to:
   - Update and build `asuntomyynti-react`
   - Sync assets into the Drupal `asu_apartment_search` module
   - Use the documented `make` targets for troubleshooting and verification
3. When the user asks to run `/update-asuntomyynti-react-local-dev`:
   - Treat that as “apply the workflow described in the .agents skill file”
   - Describe briefly which steps you’re running and why

