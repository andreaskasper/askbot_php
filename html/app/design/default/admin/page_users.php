<?php
/**
 * admin/page_users.php - roles and suspensions.
 */
i18n::init(__FILE__);

$db = new SQL(0);
$query = trim((string)($_GET["q"] ?? ""));
$where = "deleted_at IS NULL";
$values = [];
if ($query !== "") { $where .= ' AND (username LIKE "%{0}%" OR email LIKE "%{0}%")'; $values[] = $query; }
$rows = $db->cmdrows('SELECT * FROM users WHERE ' . $where . ' ORDER BY id DESC LIMIT 100', $values);

PageEngine::html("header", ["title" => __("Users"), "noindex" => true]);
PageEngine::html("admin/box_nav", ["active" => "users"]);
?>
<div id="adminUsersApp" v-cloak>
  <form class="mb-3 d-flex gap-2" method="get">
    <input class="form-control form-control-sm w-auto" name="q" value="<?= htmlattr($query) ?>" placeholder="<?= htmlattr(__("name or email")) ?>">
    <button class="btn btn-sm btn-outline-secondary"><?= html(__("Search")) ?></button>
  </form>

  <table class="table table-sm align-middle">
    <thead><tr>
      <th>#</th><th><?= html(__("User")) ?></th><th><?= html(__("Email")) ?></th><th><?= html(__("Karma")) ?></th>
      <th><?= html(__("Role")) ?></th><th><?= html(__("Joined")) ?></th><th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $row) { ?>
      <tr>
        <td class="text-secondary small"><?= (int)$row["id"] ?></td>
        <td><a href="<?= htmlattr(url("users/" . (int)$row["id"] . "/" . $row["slug"])) ?>"><?= html($row["username"]) ?></a>
          <?php if ((int)$row["is_suspended"] === 1) { ?><span class="badge text-bg-warning"><?= html(__("suspended")) ?></span><?php } ?>
          <?php if ($row["email_verified_at"] === null) { ?><span class="badge text-bg-secondary"><?= html(__("unverified")) ?></span><?php } ?>
        </td>
        <td class="small text-secondary"><?= html($row["email"]) ?></td>
        <td><?= (int)$row["karma"] ?></td>
        <td>
          <select class="form-select form-select-sm" @change="setRole(<?= (int)$row["id"] ?>, $event.target.value)" <?= (int)$row["id"] === MyUser::id() ? "disabled" : "" ?>>
            <?php foreach (["user" => __("member"), "moderator" => __("moderator"), "admin" => __("admin")] as $value => $label) { ?>
              <option value="<?= htmlattr($value) ?>" <?= (string)$row["role"] === $value ? "selected" : "" ?>><?= html($label) ?></option>
            <?php } ?>
          </select>
        </td>
        <td class="small text-secondary"><?= html(i18n::date((string)$row["created_at"], "Y-m-d")) ?></td>
        <td class="text-end text-nowrap">
          <?php if ((int)$row["is_suspended"] === 1) { ?>
            <button class="btn btn-sm btn-outline-secondary" @click="unsuspend(<?= (int)$row["id"] ?>)"><?= html(__("Lift")) ?></button>
          <?php } elseif ((int)$row["id"] !== MyUser::id()) { ?>
            <button class="btn btn-sm btn-outline-danger" @click="suspend(<?= (int)$row["id"] ?>)"><?= html(__("Suspend")) ?></button>
          <?php } ?>
        </td>
      </tr>
    <?php } ?>
    </tbody>
  </table>
</div>

<script>
const { createApp } = Vue;
createApp({
  methods: {
    async setRole(id, role) {
      try { await askbot.api("admin.setrole", { id, role }); askbot.toast(askbot.i18n.saved); }
      catch (e) { askbot.toast(e.message, "danger"); }
    },
    async suspend(id) {
      const days = prompt(<?= json_encode(__("Suspend for how many days? (0 = indefinitely)")) ?>, "7");
      if (days === null) return;
      const reason = prompt(<?= json_encode(__("Reason (the user will see it)")) ?>, "");
      try { await askbot.api("admin.suspenduser", { id, days, reason }); location.reload(); }
      catch (e) { askbot.toast(e.message, "danger"); }
    },
    async unsuspend(id) {
      try { await askbot.api("admin.unsuspenduser", { id }); location.reload(); }
      catch (e) { askbot.toast(e.message, "danger"); }
    }
  }
}).mount("#adminUsersApp");
</script>
<?php PageEngine::html("footer"); ?>
