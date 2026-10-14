# Save local SPMU changes and review a merge from GitHub main

Local checkpoint branch: backup/local-spmu-20261014-072648. Starting main commit: 503dfd0c43e2e51bb479c0958fe8ad345e10af1c. Private local backup folder: C:/Users/Admin/SPMU-Backups/20261014-072648-before-main-sync. This checkpoint preserves the existing code, additions, deletions, tests and documentation; it is not a deployment or release commit.

## Before pulling

The current local changes are committed on the checkpoint branch. A Git bundle provides a second recovery copy outside the repository. Google/mail .env files are Git-ignored and backed up privately; they are not committed or pushed. The local preview database and its private storage are also backed up. Read-only project references and QA evidence stay in the original project mirror.

Keep the checkpoint branch checked out to inspect the updated local system at http://127.0.0.1:8765/login. The preview serves whichever source branch is checked out in C:/Users/Admin/capstone-spmu-acpmp. Changing branches changes that source; check dependency compatibility before testing a different branch.

## Pull main, then prepare a review branch

Run these commands from the VS Code terminal in this repository after checking that git status is clean:

~~~powershell
git status
git switch main
git pull --ff-only origin main
git switch -c review/spmu-main-merge-20261014-072648
git merge --no-commit --no-ff backup/local-spmu-20261014-072648
~~~

The fast-forward pull stops if local main has diverged; investigate that condition instead of forcing/resetting main. --no-commit --no-ff leaves the proposed merge open for review even if Git can resolve it automatically. If main has no changes and Git says Already up to date, there may be no merge to commit.

## Choose what to retain in VS Code

1. Open Source Control. For files in Merge Changes, open Resolve in Merge Editor.
2. In this exact branch sequence, **Current = updated GitHub/main code** and **Incoming = saved local checkpoint code**. Confirm the branch names shown by the editor.
3. Choose Current, Incoming or combine specific sections in Result. Review the resulting complete function/template, not just the marked lines. Do not apply Accept All Current/Incoming across the repository.
4. Review **all staged changes**, including files Git merged automatically. Inspect them in Source Control or with git diff --cached; nonconflicting changes are not guaranteed to be logically compatible.
5. Check authentication/roles, external affiliation, approval and E-sign routing, inventory transactions, descriptive-only analytics, migrations, composer.json/composer.lock, routes and their acceptance tests together. Respect the confirmed Admin/SAO authority and preserve historical signed evidence.
6. Resolve dependency or migration conflicts before installing selected dependencies or applying migrations. Do not run migrate:fresh, reset --hard, clean -fd, or overwrite the kept preview database/private .env to resolve code conflicts.
7. Mark each reviewed file resolved, then inspect the final diff and run regression tests using isolated test databases:

~~~powershell
git add path/to/reviewed-file.php
git diff --cached
git diff --cached --check
git status
~~~

The latest pre-merge QA baseline is 1438/1438 backend cases on each of SQLite and MariaDB11.4.12,11994 assertions each; frontend190 passed/1 existing optional skip. Those results do not certify the new merged combination; rerun the applicable checks after resolving conflicts. The development partner can help run the complete isolated suites after the merge result is ready.

## Finish, abort, or restore

After reviewing and testing the merged result:

~~~powershell
git commit -m "Merge reviewed SPMU local enhancements with updated main"
~~~

This creates a local merge commit on the review branch. After approval, local main can fast-forward to it:

~~~powershell
git switch main
git merge --ff-only review/spmu-main-merge-20261014-072648
~~~

Pushing to GitHub is a separate explicit action. This saving step does not fetch, pull, merge or push the remote repository.

To abandon an in-progress merge before committing:

~~~powershell
git merge --abort
git switch backup/local-spmu-20261014-072648
~~~

To return to the saved local code when no merge is in progress, switch to the checkpoint branch with a clean working tree. Save any newer uncommitted work first. If the original repository is lost, verify/clone the external Git bundle into a new folder, then switch to the checkpoint branch and install dependencies. Restore private configuration, database and private storage only from the secured backup; keep them out of Git. Never restore private credentials into a shared chat or public artifact.
