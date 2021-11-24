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

The `develop` branch is the development branch which means it contains the next version to be released.  `stable` contains the current latest release and `master` contains the corresponding stable development version.  Always work on the `develop` branch and open up PRs against `develop`.

## Release instructions

1. If the new version requires a reindex, add its number to the `$reindex_versions` array in the `ElasticPress\Upgrades::check_reindex_needed()` method.  If it is the case, remember to add that information to the Changelog listings in `readme.txt` and `CHANGELOG.md`.
1. Branch: Starting from `develop`, cut a release branch named `release/X.Y.Z` for your changes.
1. Version bump: Bump the version number in `elasticpress.php`, `package.json`, `package-lock.json`, `readme.txt`, and any other relevant files if it does not already reflect the version being released.  In `elasticpress.php` update both the plugin "Version:" property and the plugin `EP_VERSION` constant.
1. Changelog: Add/update the changelog in `CHANGELOG.md` and `readme.txt`, ensuring to link the [X.Y.Z] release reference in the footer of `CHANGELOG.md` (e.g., https://github.com/10up/ElasticPress/compare/X.Y.Z-1...X.Y.Z).
1. Props: Update `CREDITS.md` file with any new contributors, confirm maintainers are accurate.
1. Readme updates: Make any other readme changes as necessary.  `README.md` is geared toward GitHub and `readme.txt` contains WordPress.org-specific content.  The two are slightly different.
1. New files: Check to be sure any new files/paths that are unnecessary in the production version are included in `.gitattributes`.
1. Merge: Merge the release branch/PR into `develop`, then make a non-fast-forward merge from `develop` into `master` (`git checkout master && git merge --no-ff develop`).  `master` contains the stable development version.
1. Test: While still on the `master` branch, test for functionality locally.
1. Push: Push your `master` branch to GitHub (e.g. `git push origin master`).
1. Release: Create a [new release](https://github.com/10up/elasticpress/releases/new), naming the tag and the release with the new version number, and targeting the `master` branch.  Paste the release changelog from `CHANGELOG.md` into the body of the release and include a link to the closed issues on the [milestone](https://github.com/10up/elasticpress/milestone/#?closed=1).
1. SVN: Wait for the [GitHub Action](https://github.com/10up/ElasticPress/actions?query=workflow%3A%22Deploy+to+WordPress.org%22) to finish deploying to the WordPress.org repository.  If all goes well, users with SVN commit access for that plugin will receive an emailed diff of changes.
1. Check WordPress.org: Ensure that the changes are live on https://wordpress.org/plugins/elasticpress/.  This may take a few minutes.
1. Close milestone: Edit the [milestone](https://github.com/10up/elasticpress/milestone/#) with release date (in the `Due date (optional)` field) and link to GitHub release (in the `Description` field), then close the milestone.
1. Punt incomplete items: If any open issues or PRs which were milestoned for `X.Y.Z` do not make it into the release, update their milestone to `X.Y.Z+1`, `X.Y+1.0`, `X+1.0.0` or `Future Release`.

## Hotfix release instructions

There may be cases where we have an urgent/important fix that ideally gets into a release quickly without any other changes (e.g., a "hotfix") so as to reduce (1) the amount or testing before being confident in the release and (2) to reduce the chance of unintended side effects from the extraneous non-urgent/important changes.  In cases where code has previously been merged into `develop` but that ideally is not part of a hotfix, the normal release instructions above will not suffice as they would release all code merged to `develop` alongside the intended urgent/important "hotfix" change(s).  In case of needing to release a "hotfix" the following are the recommended steps to take.

1. If the new version requires a reindex, add its number to the `$reindex_versions` array in the `ElasticPress\Upgrades::check_reindex_needed()` method.  If it is the case, remember to add that information to the Changelog listings in `readme.txt` and `CHANGELOG.md`.
1. Branch: Starting from `master`, cut a hotfix release branch named `hotfix/X.Y.Z` for your hotfix change(s).
1. Version bump: Bump the version number in `elasticpress.php`, `package.json`, `readme.txt`, and any other relevant files if it does not already reflect the version being released.  In `elasticpress.php` update both the plugin "Version:" property and the plugin `EP_VERSION` constant.
1. Changelog: Add/update the changelog in `CHANGELOG.md` and `readme.txt`, ensuring to link the [X.Y.Z] release reference in the footer of `CHANGELOG.md` (e.g., https://github.com/10up/ElasticPress/compare/X.Y.Z-1...X.Y.Z).
1. Props: Update `CREDITS.md` file with any new contributors, confirm maintainers are accurate.
1. Readme updates: Make any other readme changes as necessary.  `README.md` is geared toward GitHub and `readme.txt` contains WordPress.org-specific content.  The two are slightly different.
1. New files: Check to be sure any new files/paths that are unnecessary in the production version are included in `.gitattributes`.
1. Merge: Merge the release branch/PR into `master`.  `master` contains the stable development version.
1. Test: While still on the `master` branch, test for functionality locally.
1. Push: Push your `master` branch to GitHub (e.g. `git push origin master`).
1. Release: Create a [new release](https://github.com/10up/elasticpress/releases/new), naming the tag and the release with the new version number, and targeting the `master` branch.  Paste the release changelog from `CHANGELOG.md` into the body of the release and include a link to the closed issues on the [milestone](https://github.com/10up/elasticpress/milestone/#?closed=1).
1. SVN: Wait for the [GitHub Action](https://github.com/10up/ElasticPress/actions?query=workflow%3A%22Deploy+to+WordPress.org%22) to finish deploying to the WordPress.org repository.  If all goes well, users with SVN commit access for that plugin will receive an emailed diff of changes.
1. Check WordPress.org: Ensure that the changes are live on https://wordpress.org/plugins/elasticpress/.  This may take a few minutes.
1. Close milestone: Edit the [milestone](https://github.com/10up/elasticpress/milestone/#) with release date (in the `Due date (optional)` field) and link to GitHub release (in the `Description` field), then close the milestone.
1. Punt incomplete items: If any open issues or PRs which were milestoned for `X.Y.Z` do not make it into the hotfix release, update their milestone to `X.Y.Z+1`, `X.Y+1.0`, `X+1.0.0` or `Future Release`.
1. Apply hotfix changes to `develop`: Make a non-fast-forward merge from `master` into `develop` (`git checkout develop && git merge --no-ff master`) to ensure your hotfix change(s) are in sync with active development.