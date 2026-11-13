<?php
/**
 * Activity timeline (lead_activities), newest first.
 * @var list<array<string,mixed>> $activities
 * @var bool $showEnquiry  link each entry to its enquiry (customer profile)
 */
use App\Repositories\LeadActivityRepository as Act;

$showEnquiry = $showEnquiry ?? false;
?>
<?php if ($activities === []): ?>
  <p class="text-secondary mb-0">Nothing logged yet. Add a note after every call or WhatsApp chat so the whole team knows where things stand.</p>
<?php else: ?>
  <ul class="rth-crm-timeline list-unstyled mb-0">
    <?php foreach ($activities as $a): $type = (string) $a['type']; ?>
      <li class="rth-crm-timeline-item">
        <span class="rth-crm-timeline-icon bg-<?= e(Act::colour($type)) ?>-lt" title="<?= e(Act::label($type)) ?>"><?= icon(Act::icon($type), 'icon icon-sm') ?></span>
        <div class="rth-crm-timeline-body">
          <div class="small text-secondary">
            <strong class="text-body"><?= e(Act::label($type)) ?></strong>
            · <?= e(nice_date($a['created_at'])) ?>
            <?php if (!empty($a['admin_name'])): ?> · <?= e($a['admin_name']) ?><?php endif; ?>
            <?php if ($showEnquiry && !empty($a['enquiry_id'])): ?>
              · <a href="<?= e(url('/enquiries/' . $a['enquiry_id'])) ?>">Enquiry #<?= e($a['enquiry_id']) ?></a>
            <?php endif; ?>
          </div>
          <div class="rth-crm-pre"><?= e($a['body']) ?></div>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
