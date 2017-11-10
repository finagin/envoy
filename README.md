# envoy - deployment script
## Usage
1) Your must have Envoy installed using the Composer global command:
	```bash
	composer global require "laravel/envoy=~1.0"
	```
0) Clone `Envoy.blade.php` into root project directory
	* Using `curl`
		```bash
		curl -sSOL https://cdn.rawgit.com/finagin/envoy/master/Envoy.blade.php
		```
	* Or `wget`
		```bash
		wget -o- curl -sSL https://cdn.rawgit.com/finagin/envoy/master/Envoy.blade.php
		```
0) Create `.env.deploy` if u want.
0) Setting up enviroment variables in `.env` file.
    * `DEPLOY_SERVER`
    * `DEPLOY_REPOSITORY`
    * `DEPLOY_PATH`
    * Like this:
    	```bash
    	DEPLOY_SERVER=deploy@earth
	  	DEPLOY_REPOSITORY=git@github.com:finagin/mailer.git
	  	DEPLOY_PATH=/var/www/mailer
		```
	* ***Important!*** Make sure that the user has permissions to clone the repository
0) Run command `init` to initialize project on server
	```bash
	envoy run init
	```
0) Edit `$DEPLOY_PATH/.env` file on server if u need. ex:`/var/www/mailer/.env`
0) Run `deploy` command for **first** deploy.
	```bash
	envoy run deploy
	```
	Also u can use options like `--branch=BRANCH` and `--env=ENV`
0) After **second** deploy u may use `rollback` command
	```bash
	envoy run rollback
	```
