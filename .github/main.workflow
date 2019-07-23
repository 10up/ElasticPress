workflow "Deploy" {
  resolves = ["WordPress Plugin Deploy"]
  on = "push"
}

# Filter for tag
action "tag" {
  uses = "actions/bin/filter@master"
  args = "tag"
}

action "WordPress Plugin Deploy" {
  needs = ["tag"]
  uses = "10up/actions-wordpress/dotorg-plugin-deploy@master"
  secrets = ["SVN_PASSWORD", "SVN_USERNAME", "GITHUB_TOKEN"]
}

workflow "Asset/readme update" {
  resolves = ["Plugin Asset Update"]
  on = "push"
}

action "Filters for GitHub Actions" {
  uses = "actions/bin/filter@master"
  args = "branch master"
}

action "Plugin Asset Update" {
  uses = "10up/actions-wordpress/dotorg-plugin-asset-update@master"
  needs = ["Filters for GitHub Actions"]
  secrets = ["SVN_USERNAME", "SVN_PASSWORD"]
}
