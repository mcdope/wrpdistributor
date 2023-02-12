### What the heck is this? ### 

WrpDistributor is a small application to allow https://github.com/tenox7/wrp to be used by multiple clients via `AmiFox`,
or any application implementing it (looking at you, Atari crowd 👏)

### License ###

This whole project (regarding this script to distribute session to `wrp`) is licensed under AGPLv3. You can find 
the license text at https://www.gnu.org/licenses/agpl-3.0.de.html. Basically it means it's opensource and you can do 
whatever you want, as long as you make your changes opensource again. Of course preferred as a pull request, but to be 
compliant it's enough to publish your sources. And yes, this also applies if you don't distribute it - it's AGPL, not GPL.

Note that this DOES NOT apply to `AmiFox` (the reference/main implementation) itself, which is a closed source project.

### How do I set it up? ###

#### The easy way ####
Download the repository, open a shell and change to the repository. Now...
- run `cp .env.dist .env`
- edit `.env` and set your parameters
    - the defaults are assuming you run it via the provided docker image, so you only need to change:
       - `MAX_CONTAINERS_RUNNING`
       - `CONTAINER_HOSTS`
       - `CONTAINER_HOSTS_KEYS`
       - `AUTH_TOKEN`
       - ... and maybe `START_PORT`
- run `make build` to create the docker container
- run `make up`, it will start the container and provide `wrp-distributor` on the public port `7777`
- actually set up at least one containerHost
    - configure the server of your choice (as long as it's Linux 😅) with Docker
    - add a user dedicated to run the containers, add it to the group `docker` to allow it to manage containers
        - IMPORTANT: this allows this user to manage ALL containers on that host, so you most likely want to put this in a VM itself, lock this user down further and allow logins only from your expected ingress IP. It could very well happen that this software has a catastrophic bug exposing the SSH keys, even if unlikely. This software is NOT INTENDED FOR ENDUSERS and requires profound knowledge to operate it in a secure manner. I wont go into details of this, because if I need to you're not the target group to be honest.
    - create a passwordless SSH keypair to allow `wrp-distributor` to access the host(s). If you configure multiple hosts you should a dedicated keypair for each of them. Then install this key with `ssh-copy-id` to the user account of the host
    - make sure you can ssh with that key into the host
    - make sure you can run docker with that user on that host (i.e run the helloworld container)
    - put the keypair into `./ssh`
    - adjust your `.env`
       - add host to `CONTAINER_HOSTS`, either as DNS name or IP
       - add authentication data to `CONTAINER_HOSTS_KEYS`
           - format is `userName~filenameOfPrivateKey`
- Repeat previous step for as many hosts as you want, the architecture of this distributor can easily handle more requests then you can provide hosts to run on. Except you're some cloud provider
- In case you're running this in a production setup: provide a reverse proxy to expose `wrp-distributor` via your default webserver. Make sure you don't leak sensitive data like SSH keys etc.

#### The hard way ####

You will need MySQL 8, PHP8.2 and composer on your server to run this. 

- put `wrp-distributor` in the content root you want to use
- open a shell and switch to your content root
- run `cp .env.dist .env`
- run `composer install` to install the dependencies
- create a database and dbuser for `wrp-distributor` and adjust `.env` accordingly
    - required tables will be auto-created if missing, don't worry about it. But if you insist on it, run [sessions.sql](db/sessions.sql) on it
- adjust remaining parameters in `.env` according to your needs
- continue with "actually set up at least one containerHost" from [The easy way](README.md#the-easy-way-)



### How do I use it? 

See [INTEGRATION.md](INTEGRATION.md)

#### Huh? I don't get it... ####

Then this software isn't for you. You're most likely looking for https://github.com/tenox7/wrp instead.

### How do I...? ###

#### Configure my containerHosts in a secure way ####

You won't, if you need to ask. There is too much to be missed and profound knowledge about server setup required.

Points you should look into:
- limit user to only `docker`, disallow everything else (though the ability to use docker kinda defeats that)
- disallow password login for that user
- allow login only from the expected ingress IP (= your `wrp-distributor` host)

### TODO ###
- Implement unused session cleanup
- Implement load balancing for containerHosts
    - bestcase: check available resources and dynamically scale
    - easycase: make MAX_CONTAINERS per containerHost 
- maybe provide `make` targets to run test requests
- configure `nginx` container to disallow serving `./ssh`, `./docker` and `logs` to make sure people don't mess up because they use the unmodified dev docker for production usage
- add support for password protected SSH keys
- add purpose-bound Exception classes