# Emarsys Magento 2 Extension

For development information refer to [dev README](dev/README.md).

## Release
To create a new release follow these steps:
* Bump the plugin version in `composer.json`, commit, tag with version number and push. (Optionally tagging can be made on the UI later.)
* Go the repo on GitHub, click **releases** tab and click **Draft new release** button.
* If you have tagged the version earlier choose it from the dropdown, otherwise enter the new version number in the field.
* Add release title and optionally description.
* Click **Publish release** button.

## Codeship env
* [Install](https://documentation.codeship.com/pro/jet-cli/installation/) `jet`
* Download the `aes` key from [Codeship](https://app.codeship.com/projects/290273/configure) into the project directory.
* Run `$ jet encrypt codeship.env codeship.env.encrypted`
* Commit `codeship.env.encrypted` into the repo.