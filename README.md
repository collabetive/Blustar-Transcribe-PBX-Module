# BluStar Transcription for FreePBX

[![Build, Sign, and Release](https://github.com/collabetive/Blustar-Transcribe-PBX-Module/actions/workflows/main.yml/badge.svg)](https://github.com/collabetive/Blustar-Transcribe-PBX-Module/actions/workflows/main.yml)

Automatically send your call recordings to BluStar for transcription — the moment each call ends.

No manual uploads. No batch jobs. Just hang up the phone, and the transcript is on its way.

## What you get

- **Automatic transcription of every recorded call.** As soon as a call finishes, the recording is sent to your BluStar transcription server.
- **A simple settings page.** Point the module at your server, paste in your API key, and you're done.
- **Built-in connection test.** One click confirms the server is reachable and your API key works.
- **Transfer history at a glance.** See which recordings uploaded successfully and which didn't, with timestamps and error messages.
- **Automatic log cleanup.** Old transfer records are purged on a schedule you control.

## Installing

Download the latest signed release from the [Releases page](https://github.com/collabetive/Blustar-Transcribe-PBX-Module/releases) and install it through **Admin → Module Admin → Upload Modules** in your FreePBX UI.

After install, apply config when FreePBX prompts you.

## Setting it up

1. In FreePBX, go to **Admin → BluStar Transcription**.
2. Fill in the settings:
   - **Enabled** — turn automatic uploads on.
   - **Server Host** — the address of your BluStar transcription server.
   - **Server Port** — defaults to `8600`.
   - **API Key** — provided by your BluStar administrator.
   - **Log Retention** — how many days of transfer history to keep.
3. Click **Test Connection** to confirm everything is wired up.
4. Click **Save Settings**.

That's it. The next call recorded on your PBX will be uploaded automatically.

## Checking that it's working

Open the **Transfer Logs** tab on the module page. You'll see a list of every recording the module has tried to send, with its status — success, or the reason it failed.

If something isn't uploading:

- Make sure **Enabled** is set to *Yes*.
- Run **Test Connection** — if it fails, the error message will tell you what to fix (bad host, wrong port, rejected key, firewall, etc.).
- Confirm your extensions actually have **call recording** turned on in FreePBX. The module only uploads calls that FreePBX has recorded.

## Requirements

- FreePBX 17.0 or newer
- Call recording enabled on the extensions or routes you want transcribed
- Network access from your PBX to your BluStar transcription server

## Support

For help, licensing questions, or to request an API key, contact The Collabetive.

---

© The Collabetive. Proprietary software.
