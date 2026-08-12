<?php
/**
 * admin/page_statistics.php
 */
i18n::init(__FILE__);

$overview = Statistics::overview();
$timeline = Statistics::timeline(30);

PageEngine::html("header", ["title" => __("Statistics"), "noindex" => true]);
PageEngine::html("admin/box_nav", ["active" => "statistics"]);
?>
<h1 class="h4 mb-3"><?= html(__("Statistics")) ?></h1>

<div class="row row-cols-2 row-cols-md-4 g-3 mb-4">
  <?php foreach ([
      __("Questions") => $overview["questions"],
      __("Answers") => $overview["answers"],
      __("Users") => $overview["users"],
      __("Tags") => $overview["tags"],
      __("Unanswered") => $overview["unanswered"],
      __("Accepted rate") => $overview["accepted_rate"] . " %",
      __("Active today") => $overview["active_today"],
      __("Open flags") => $overview["open_flags"],
  ] as $label => $value) { ?>
    <div class="col"><div class="card h-100"><div class="card-body py-3 text-center">
      <div class="fs-4 fw-semibold"><?= html(is_int($value) ? number_format($value) : (string)$value) ?></div>
      <div class="small text-secondary"><?= html($label) ?></div>
    </div></div></div>
  <?php } ?>
</div>

<div class="card mb-4">
  <div class="card-header py-2 fw-semibold"><?= html(__("Last 30 days")) ?></div>
  <div class="card-body">
    <table class="table table-sm mb-0">
      <thead><tr><th><?= html(__("Day")) ?></th><th><?= html(__("Questions")) ?></th><th><?= html(__("Answers")) ?></th><th></th></tr></thead>
      <tbody>
      <?php $max = max(1, max(array_column($timeline, "questions") + array_column($timeline, "answers"))); ?>
      <?php foreach (array_reverse($timeline) as $day) { ?>
        <tr>
          <td class="small text-secondary"><?= html($day["date"]) ?></td>
          <td><?= (int)$day["questions"] ?></td>
          <td><?= (int)$day["answers"] ?></td>
          <td style="width: 50%">
            <div class="progress" style="height: .5rem">
              <div class="progress-bar" style="width: <?= (int)round($day["questions"] * 100 / $max) ?>%"></div>
              <div class="progress-bar bg-success" style="width: <?= (int)round($day["answers"] * 100 / $max) ?>%"></div>
            </div>
          </td>
        </tr>
      <?php } ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <div class="card-header py-2 fw-semibold"><?= html(__("Recent administrative actions")) ?></div>
  <div class="card-body p-0">
    <table class="table table-sm mb-0">
      <tbody>
      <?php foreach (Audit::recent(40) as $entry) { ?>
        <tr>
          <td class="small text-secondary"><?= html(i18n::date((string)$entry["created_at"], "Y-m-d H:i")) ?></td>
          <td class="small"><?= html($entry["action"]) ?></td>
          <td class="small text-secondary"><?= html($entry["target"]) ?></td>
        </tr>
      <?php } ?>
      </tbody>
    </table>
  </div>
</div>
<?php PageEngine::html("footer"); ?>
