# TODO

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
- [x] Go through existing codebase
  - [x] Understand the execution flow
    - [x] Add an endpoindpoint to make sure we understand
  - [x] Add comments and do basic resuffle (you know we'll have to)
- [x] Create routes combiner 
- [x] Create autowiring combiner 
- [ ] Create settings combiner
- [ ] Module: Persistence
  - [ ] Create abstract persistence class
  - [x] Get SQLite working
    - [x] Download, install blabla
    - [x] Write concrete subclass 
  - [x] Start creating tables
  - [ ] Write short example MySQL subclass to demonstrate swapability
- [ ] Module: Inventory
  - [ ] DB tables: studio, games, ledger
  - [ ] CRUD class
    - [ ] reserve/revert inventory      
    - [ ] buy/sell inventory
  - [ ] Scrape games from some store and populate DB
  - [ ] Action class
    - [ ] search games
    - [ ] list games (all, genre, top)
- [ ] Module: Orders
  - [ ] DB tables: orders_head, orders_rows
  - [ ] CRUD class
  - [ ] Action class
    - [ ] place order (checks inventory and reserves quantity)
    - [ ] pay order (reverts reservation, reduces inventory)
    - [ ] cancel order (reverts reservation or inventory based on status)
- [ ] Module: Users
  - [ ] DB tables: users
  - [ ] CRUD class
  - [ ] Action class
    - [ ] register
    - [ ] login (create JWT)
  - [ ] Setup JWT 
    - [ ] Parse in middleware
- [ ] Module: Statistics
