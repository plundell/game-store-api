# Game Store API

An example of a stateless API, built on PHP and the [Slim Framework](https://www.slimframework.com/).

The API will provide a simple backend for an online video game store with the following capabilities:
 - store portfolio of games with descriptions etc.
 - keep track of inventory for those games
 - enable placing orders for those games, warning about lack of inventory

Possible bonus features may include:
 - allow users to register and login
 - provide sales statistics of various kinds
   - trending games
   - trending genres
   - often bought together (relies on registered users)


## Workflow
- [x] Setup IDE and API framework
  - [x] Remind self how PHP works, how it's configured, and how to setup projects
  - [x] Configure VS Code with suitable extensions
    - [x] Fight with language server which won't warn on unset variables until you realize that just applies to globally scoped variables
  - [x] Find a suitable framework
    - [x] Decide Laravel is too bloated and focused on MVC paradigm and backend rendered clients
    - [x] Try what you think is it's little brother Lumen until you realize that's been discontinued and installing it seems to leave you in dependency conflict hell
    - [x] Try this thing called Google and find [Slim Framework](https://www.slimframework.com/) who's website is exactly simple enough to convince you _yay, no overhead, just bare bones_.
  - [x] Download _Slim_ and make sure it works
    ```bash
    $ composer create-project slim/slim-skeleton game-store-api 
    $ cd game-store-api
    $ composer start #open browser to localhost:8080 - success
    #realize slim came with testing setup #winning
    $ composer test 
    ```
- [ ] Go through existing codebase
  - [ ] Understand the execution flow
    - [ ] Add an endpoindpoint to make sure we understand
  - [ ] Add comments and do basic resuffle (you know we'll have to)
- [ ] Module: Persistence
- [ ] Module: Inventory
- [ ] Module: Orders
- [ ] Module: Users
- [ ] Module: Statistics


