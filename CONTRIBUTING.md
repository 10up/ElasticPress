# Contributing and Maintaining

First, thank you for taking the time to contribute!

The following is a set of guidelines for contributors as well as information and instructions around our maintenance process.  The two are closely tied together in terms of how we all work together and set expectations, so while you may not need to know everything in here to submit an issue or pull request, it's best to keep them in the same document.

## Ways to contribute

Contributing isn't just writing code - it's anything that improves the project.  All contributions are managed right here on GitHub.  Here are some ways you can help:

### Reporting bugs

If you're running into an issue, please take a look through [existing issues](https://github.com/10up/elasticpress/issues) and [open a new one](https://github.com/10up/elasticpress/issues/new) if needed.  If you're able, include steps to reproduce, environment information, and screenshots/screencasts as relevant.

### Suggesting enhancements

New features and enhancements are also managed via [issues](https://github.com/10up/elasticpress/issues).

### Pull requests

Pull requests represent a proposed solution to a specified problem.  They should always reference an issue that describes the problem and contains discussion about the problem itself.  Discussion on pull requests should be limited to the pull request itself, i.e. code review.

For more on how 10up writes and manages code, check out our [10up Engineering Best Practices](https://10up.github.io/Engineering-Best-Practices/).

## Workflow

The `develop` branch is the development branch which means it contains the next version to be released. `trunk` contains the corresponding stable development version. Always work on the `develop` branch and open up PRs against `develop`.

## Release instructions

Open a [new blank issue](https://github.com/10up/ElasticPress/issues/new) with `[Release] X.Y.Z`, then copy and paste the following items, replacing version numbers and links to the milestone.

- [ ] 1. If the new version requires a reindex, add its number to the `$reindex_versions` array in the `ElasticPress\Upgrades::check_reindex_needed()` method. If it is the case, remember to add that information to the Changelog listings in `readme.txt` and `CHANGELOG.md`.
- [ ] 2. Branch: Starting from `develop`, cut a release branch named `release/X.Y.Z` for your changes.
- [ ] 3. Version bump: Bump the version number in `elasticpress.php`, `package.json`, `package-lock.json`, `readme.txt`, and any other relevant files if it does not already reflect the version being released. In `elasticpress.php` update both the plugin "Version:" property and the plugin `EP_VERSION` constant.
- [ ] 4. Changelog: Add/update the changelog in `CHANGELOG.md` and `readme.txt`, ensuring to link the [X.Y.Z] release reference in the footer of `CHANGELOG.md` (e.g., https://github.com/10up/ElasticPress/compare/X.Y.Z-1...X.Y.Z).
- [ ] 5. Props: Update `CREDITS.md` file with any new contributors, confirm maintainers are accurate.
- [ ] 6. Readme updates: Make any other readme changes as necessary. `README.md` is geared toward GitHub and `readme.txt` contains WordPress.org-specific content. The two are slightly different.
- [ ] 7. New files: Check to be sure any new files/paths that are unnecessary in the production version are included in `.distignore`.
- [ ] 8. POT file: Run `wp i18n make-pot . lang/elasticpress.pot` and commit the file. In case of errors, try to disable Xdebug (see [#3079](https://github.com/10up/ElasticPress/pull/3079#issuecomment-1291028290).)
- [ ] 9. Release date: Double check the release date in both changelog files.
- [ ] 10. Merge: Merge the release branch/PR into `develop`, then make a non-fast-forward merge from `develop` into `trunk` (`git checkout trunk && git merge --no-ff develop`). `trunk` contains the stable development version.
- [ ] 11. Test: While still on the `trunk` branch, test for functionality locally.
- [ ] 12. Push: Push your `trunk` branch to GitHub (e.g. `git push origin trunk`).
- [ ] 13. [Check the _Build and Tag_ action](https://github.com/10up/ElasticPress/actions/workflows/build-and-tag.yml): a new tag named with the version number should've been created. It should contain all the built assets.
- [ ] 14. Release: Create a [new release](https://github.com/10up/elasticpress/releases/new):
  * **Tag**: The tag created in the previous step
  * **Release title**: `Version X.Y.Z`
  * **Description**: Release changelog from `CHANGELOG.md` + `See: https://github.com/10up/ElasticPress/milestone/#?closed=1`
- [ ] 15. SVN: Wait for the [GitHub Action](https://github.com/10up/ElasticPress/actions/workflows/push-deploy.yml) to finish deploying to the WordPress.org repository. If all goes well, users with SVN commit access for that plugin will receive an emailed diff of changes.
- [ ] 16. Check WordPress.org: Ensure that the changes are live on https://wordpress.org/plugins/elasticpress/. This may take a few minutes.
- [ ] 17. Close milestone: Edit the [milestone](https://github.com/10up/elasticpress/milestone/#) with release date (in the `Due date (optional)` field) and link to GitHub release (in the `Description` field), then close the milestone.
- [ ] 18. Punt incomplete items: If any open issues or PRs which were milestoned for `X.Y.Z` do not make it into the release, update their milestone to `X.Y.Z+1`, `X.Y+1.0`, `X+1.0.0` or `Future Release`

## Pre-release instructions (betas, release candidates, etc)

Pre-releases are different from normal versions because (1) they are not published on WordPress.org and (2) they are usually created from a branch different from `develop`.

1. If the new version requires a reindex, add its number to the `$reindex_versions` array in the `ElasticPress\Upgrades::check_reindex_needed()` method.  If it is the case, remember to add that information to the Changelog listings in `readme.txt` and `CHANGELOG.md`.
1. Branch: Starting from the next version branch, for example, `4.x.x`, cut a release branch named `release/X.Y.Z` for your changes.
1. Version bump: Bump the version number in `elasticpress.php`, `package.json`, `package-lock.json`, `readme.txt`, and any other relevant files if it does not already reflect the version being released.  In `elasticpress.php` update both the plugin "Version:" property and the plugin `EP_VERSION` constant. The version should follow the `X.Y.Z-beta.A` pattern.
1. Changelog: Add/update the changelog in `CHANGELOG.md` and `readme.txt`, ensuring to link the [X.Y.Z] release reference in the footer of `CHANGELOG.md` (e.g., https://github.com/10up/ElasticPress/compare/X.Y.Z-1...X.Y.Z).
1. Props: Update `CREDITS.md` file with any new contributors, confirm maintainers are accurate.
1. Readme updates: Make any other readme changes as necessary. `README.md` is geared toward GitHub and `readme.txt` contains WordPress.org-specific content.  The two are slightly different.
1. New files: Check to be sure any new files/paths that are unnecessary in the production version are included in `.distignore`.
1. POT file: Run `wp i18n make-pot . lang/elasticpress.pot` and commit the file.
1. Release date: Double check the release date in both changelog files.
1. Merge: Merge the release branch/PR into the next version branch (`4.x.x`, for example).
1. Test: Checkout the next version branch locally and build assets like the GitHub Action will do (see `.github/workflows/push-deploy.yml`)
1. Release: Create a [new pre-release](https://github.com/10up/elasticpress/releases/new), naming the tag and the release with the new version number, and targeting the next version branch branch.  Paste the release changelog from `CHANGELOG.md` into the body of the release and include a link to the closed issues on the [milestone](https://github.com/10up/elasticpress/milestone/#?closed=1). **ATTENTION**: Make sure to check the `This is a pre-release` checkbox, so the release is not published on WordPress.org.
1. [Check the _Publish New Release_ action](https://github.com/10up/ElasticPress/actions/workflows/push-deploy.yml): After the release, GitHub should trigger an action to generate a zip with the plugin and attach it to the GitHub Release page.
1. Close milestone: Edit the [milestone](https://github.com/10up/elasticpress/milestone/#) with release date (in the `Due date (optional)` field) and link to GitHub release (in the `Description` field), then close the milestone.
1. Punt incomplete items: If any open issues or PRs which were milestoned for `X.Y.Z` do not make it into the release, update their milestone to `X.Y.Z+1`, `X.Y+1.0`, `X+1.0.0` or `Future Release`.

## Hotfix release instructions

There may be cases where we have an urgent/important fix that ideally gets into a release quickly without any other changes (e.g., a "hotfix") so as to reduce (1) the amount or testing before being confident in the release and (2) to reduce the chance of unintended side effects from the extraneous non-urgent/important changes.  In cases where code has previously been merged into `develop` but that ideally is not part of a hotfix, the normal release instructions above will not suffice as they would release all code merged to `develop` alongside the intended urgent/important "hotfix" change(s).  In case of needing to release a "hotfix" the following are the recommended steps to take.

1. If the new version requires a reindex, add its number to the `$reindex_versions` array in the `ElasticPress\Upgrades::check_reindex_needed()` method.  If it is the case, remember to add that information to the Changelog listings in `readme.txt` and `CHANGELOG.md`.
1. Branch: Starting from `trunk`, cut a hotfix release branch named `hotfix/X.Y.Z` for your hotfix change(s).
1. Version bump: Bump the version number in `elasticpress.php`, `package.json`, `readme.txt`, and any other relevant files if it does not already reflect the version being released.  In `elasticpress.php` update both the plugin "Version:" property and the plugin `EP_VERSION` constant.
1. Changelog: Add/update the changelog in `CHANGELOG.md` and `readme.txt`, ensuring to link the [X.Y.Z] release reference in the footer of `CHANGELOG.md` (e.g., https://github.com/10up/ElasticPress/compare/X.Y.Z-1...X.Y.Z).
1. Props: Update `CREDITS.md` file with any new contributors, confirm maintainers are accurate.
1. Readme updates: Make any other readme changes as necessary.  `README.md` is geared toward GitHub and `readme.txt` contains WordPress.org-specific content.  The two are slightly different.
1. New files: Check to be sure any new files/paths that are unnecessary in the production version are included in `.distignore`.
1. POT file: Run `wp i18n make-pot . lang/elasticpress.pot` and commit the file.
1. Release date: Double check the release date in both changelog files.
1. Merge: Merge the release branch/PR into `trunk`.  `trunk` contains the stable development version.
1. Test: While still on the `trunk` branch, test for functionality locally.
1. Push: Push your `trunk` branch to GitHub (e.g. `git push origin trunk`).
1. Release: Create a [new release](https://github.com/10up/elasticpress/releases/new), naming the tag and the release with the new version number, and targeting the `trunk` branch.  Paste the release changelog from `CHANGELOG.md` into the body of the release and include a link to the closed issues on the [milestone](https://github.com/10up/elasticpress/milestone/#?closed=1).
1. SVN: Wait for the [GitHub Action](https://github.com/10up/ElasticPress/actions?query=workflow%3A%22Deploy+to+WordPress.org%22) to finish deploying to the WordPress.org repository.  If all goes well, users with SVN commit access for that plugin will receive an emailed diff of changes.
1. Check WordPress.org: Ensure that the changes are live on https://wordpress.org/plugins/elasticpress/.  This may take a few minutes.
1. Close milestone: Edit the [milestone](https://github.com/10up/elasticpress/milestone/#) with release date (in the `Due date (optional)` field) and link to GitHub release (in the `Description` field), then close the milestone.
1. Punt incomplete items: If any open issues or PRs which were milestoned for `X.Y.Z` do not make it into the hotfix release, update their milestone to `X.Y.Z+1`, `X.Y+1.0`, `X+1.0.0` or `Future Release`.
1. Apply hotfix changes to `develop`: Make a non-fast-forward merge from `trunk` into `develop` (`git checkout develop && git merge --no-ff trunk`) to ensure your hotfix change(s) are in sync with active development.