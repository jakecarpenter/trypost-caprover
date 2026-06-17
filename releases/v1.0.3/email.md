---
subject: "Changelog v1.0.3 — Discord posting, smarter RSS automations, API parity"
---

# Changelog v1.0.3 — Discord posting, smarter RSS automations, API parity

By TryPost Product Team • [Release v1.0.3](https://github.com/trypostit/trypost/releases/tag/v1.0.3)

Hello! Welcome to this week's update. Here's what's new in TryPost.

## Discord is now a channel

You can connect a Discord server and schedule posts to it like any other channel. Pick the exact channel you want to post in, add mentions, and check a live preview before the message goes out. Discord engagement now flows back into your metrics too, so it sits alongside everything else. There's also a small community widget floating across the app now, in case you want to reach us or other users quickly.

## Smarter RSS automations

RSS automations now read both RSS and Atom feeds, so you can wire up more sources without hitting a wall. You can also pull dynamic variables straight from the feed into your posts, so each item templates itself instead of being copied by hand. We cleaned up a few rough edges along the way: previews show the newest item, switching between nodes opens the right config every time, and a broken feed now fails quietly instead of taking the whole automation down with it.

## Consistent settings across API and MCP

Per-platform settings like aspect ratio, TikTok privacy, Pinterest boards, and the Discord channel now behave the same whether you set them in the app, through the public API, or via MCP. Some of these used to get silently dropped when you posted from outside the app. They carry through everywhere now.

## Fixes

- Resolved npm and composer security vulnerabilities

Cheers,
Paulo from TryPost.it
