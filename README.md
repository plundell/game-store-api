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

For whom it may concern, [this](TODO.md) is/was the workflow building the API.

## Design criteria

<table style="border-collapse: collapse; width: 80%;">
  <tr>
    <th>Criteria</th>
    <th>Example/Comments</th>
    <th>Reason</th>
  </tr>
  <tr>
    <td>Separate concerns</td>
    <td>Separate <i>CRUD</i> and <i>Action</i> classes. Seperate infrastructure (framework, persistence), handling (auth, request/response), and business logic.</td>
    <td>Easy to change things without breaking stuff. Easy to test, easy to understand and makes pieces reuseable</td>
  </tr>
  <tr>
    <td>Group by functionality</td>
    <td>Build in units of functionality, i.e. keep all files related to that unit together under one folder instead of spreading out the code into many existing files. We want <code>users/users.settings.php</code>, <code>users/users.routes.php</code>, <code>users/TestUsers.php</code>. This will require build scripts which orchestrate and combine files from different units.</td>
    <td>Creates self-contained units which are easy to swap, understand and test. Makes committing and PRs much easier, and breakages less prone as multiple developers won't be touching the same files. Makes it easier when troubleshooting because you don't have to jump around between 20 folders.</td>
  </tr>
  <tr>
    <td>Name files based on contents</td>
    <td>E.g. <code>users.settings.php</code>, <code>action.abstract.php</code>, <code>persistence.interface.php</code>, <code>userActions.class.php</code></td>
    <td>At-a-glance understandable, even when just looking at file tree.</td>
  </tr>
  <tr>
    <td>Lots of comments</td>
    <td>Use docblocks to explain files, classes, methods etc. Also comment within methods what's happening and especially <b>why</b> we're doing something a particular way. Leave comments about things we've tried which didn't work.</td>
    <td>Easy for others to understand. Makes intentions clear to self and others. Makes code maintainable.</td>
  </tr>
</table>

## Code Structure

<table style="border-collapse: collapse; width: 80%;">
  <tr>
    <th>Path</th>
    <th>Description</th>
  </tr>
  <tr>
    <td><a href="public/index.php"><code>public/index.php</code></a></td>
    <td>Entry point for each request to the API. Bootstraps the app and sets up handlers which ultimately/automatically then fullfils the request.</td>
  </tr>
  <tr>
    <td><code>app/</code></td>
    <td>All files related to bootstrapping the app/framework, all required by <a href="app/bootstrap.php"><code>bootstrap.php</code></a>. Should not contain any business logic. Should not need to be changed when adding additional features. Should be usable both by the actual app and by tests.</td>
  </tr>
  <tr>
    <td><code>src/persistence</code></td>
    <td>The persistence module provides database access. An interface is provided to be used by other modules. Classes for different databases provide implementations which can chosen through settings. See the <a href="#database">database section</a> below for more information.</td>
  </tr>
  <tr>
    <td><code>src/inventory</code></td>
    <td>The inventory module deals with which games we offer, information about them, and what's in stock. See <a href="src/inventory/README.inventory.md">README.inventory.md</a> for more information.</td></td>
  </tr>

</table>

## Database
We'll be using relational DBs and a persistence layer with swapable implementations for different databases. We'll start with SQLite for ease of use.

### Tables
<table><tr>
  <th>Name</th>
    <th>Description</th>
    <th>Columns</th>
  </tr><tr>
  <td>studios</td>
      <td>The developers. Not really used except for filtering</td>
      <td>id, name</td>
  </tr><tr>
      <td>games</td>
      <td>Used for listing and searching games</td>
      <td>id, name, studio_id, genre, image, description, rating, nr_sold price, in_stock</td>
  </tr><tr>
  <td>ledger</td>
      <td> Ledger of all stock changes </td>
      <td>id, timestamp, game_id, event, quantity, related_id</td>
  </tr><tr>
    <td>users</td>
      <td>Used for auth and contact, no order or session info</td>
      <td>id, name, email, salt, pass, registered, last_login</td>
  </tr><tr>
    <td>orders_head</td>
      <td>One row per order. Keeps track of transaction itself.</td>
      <td>id, user_id, created, total, status</td>
  </tr><tr>
    <td>orders_rows</td>
      <td>Multiple rows per order, one game per row</td>
      <td>id, order_id, product_id, quantity, price, notes</td>
</tr></table>




