<?php
/**
 * Default invoice template.
 *
 * Variables injected by InvoiceRenderer::render():
 *   @var \DigitalInvoice\InvoiceData $invoice
 *   @var string                      $cur
 *   @var array<string,string>        $labels
 *   @var \Closure                    $esc          fn(?string): string
 *   @var \Closure                    $fmt          fn(?float, string): string
 *   @var \Closure                    $date         fn(?\DateTime): string
 *   @var \Closure                    $schemeLabel  fn(string): string
 *   @var \Closure                    $formatLabel  fn(string): string
 */
?>
<div class="di-invoice">

  <header class="di-header">
    <div class="di-header__meta">
      <span class="di-label"><?= $labels['invoice'] ?></span>
      <span class="di-invoice-id"><?= $esc($invoice->invoiceId) ?></span>
      <?php $fmt_label = $formatLabel($invoice->profile); ?>
      <?php if ($fmt_label): ?>
      <span class="di-format-badge"><?= $esc($fmt_label) ?></span>
      <?php endif; ?>
    </div>
    <div class="di-header__dates">
      <div><span class="di-label"><?= $labels['issue_date'] ?></span> <?= $date($invoice->issueDate) ?></div>
      <?php if ($invoice->deliveryDate): ?>
      <div><span class="di-label"><?= $labels['delivery_date'] ?></span> <?= $date($invoice->deliveryDate) ?></div>
      <?php endif; ?>
      <?php if ($invoice->dueDate): ?>
      <div><span class="di-label"><?= $labels['due_date'] ?></span> <?= $date($invoice->dueDate) ?></div>
      <?php endif; ?>
    </div>
    <div class="di-header__misc">
      <div><span class="di-label"><?= $labels['currency'] ?></span> <?= $esc($invoice->currency) ?></div>
      <?php if ($invoice->invoiceType && $invoice->invoiceType !== '380'): ?>
      <div><span class="di-label"><?= $labels['invoice_type'] ?></span> <?= $esc($invoice->invoiceType) ?></div>
      <?php endif; ?>
    </div>
  </header>

  <?php if ($invoice->notes): ?>
  <section class="di-notes">
    <?php foreach ($invoice->notes as $note): ?>
    <p class="di-note"><?= $esc($note['content']) ?></p>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>

  <section class="di-parties">

    <?php if ($invoice->seller): ?>
    <div class="di-party di-party--seller">
      <h3 class="di-party__role"><?= $labels['seller'] ?></h3>
      <strong><?= $esc($invoice->seller->name) ?></strong>
      <?php if ($invoice->seller->tradingName): ?>
      <em><?= $esc($invoice->seller->tradingName) ?></em>
      <?php endif; ?>
      <?php if ($invoice->seller->address): $a = $invoice->seller->address; ?>
      <address>
        <?= $esc($a->lineOne) ?><br>
        <?php if ($a->lineTwo): ?><?= $esc($a->lineTwo) ?><br><?php endif; ?>
        <?= $esc($a->postCode) ?> <?= $esc($a->city) ?><br>
        <?= $esc($a->countryCode) ?>
      </address>
      <?php endif; ?>
      <?php if ($invoice->seller->id): ?>
      <div class="di-identifier">
        <span class="di-label"><?= $esc($schemeLabel($invoice->seller->idType ?? '')) ?></span>
        <?= $esc($invoice->seller->id) ?>
      </div>
      <?php endif; ?>
      <?php foreach ($invoice->seller->identifiers as $gid): ?>
      <div class="di-identifier">
        <span class="di-label"><?= $esc($schemeLabel($gid['idType'])) ?></span>
        <?= $esc($gid['id']) ?>
      </div>
      <?php endforeach; ?>
      <?php foreach ($invoice->seller->taxRegistrations as $reg): ?>
      <div class="di-identifier">
        <span class="di-label"><?= $esc($schemeLabel($reg['schemeID'])) ?></span>
        <?= $esc($reg['id']) ?>
      </div>
      <?php endforeach; ?>
      <?php if ($invoice->seller->contact): $c = $invoice->seller->contact; ?>
      <div class="di-contact">
        <?php if ($c->name): ?><div><?= $esc($c->name) ?></div><?php endif; ?>
        <?php if ($c->phone): ?><div><?= $esc($c->phone) ?></div><?php endif; ?>
        <?php if ($c->email): ?><div><?= $esc($c->email) ?></div><?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($invoice->buyer): ?>
    <div class="di-party di-party--buyer">
      <h3 class="di-party__role"><?= $labels['buyer'] ?></h3>
      <strong><?= $esc($invoice->buyer->name) ?></strong>
      <?php if ($invoice->buyer->address): $a = $invoice->buyer->address; ?>
      <address>
        <?= $esc($a->lineOne) ?><br>
        <?php if ($a->lineTwo): ?><?= $esc($a->lineTwo) ?><br><?php endif; ?>
        <?= $esc($a->postCode) ?> <?= $esc($a->city) ?><br>
        <?= $esc($a->countryCode) ?>
      </address>
      <?php endif; ?>
      <?php if ($invoice->buyer->id): ?>
      <div class="di-identifier">
        <span class="di-label"><?= $esc($schemeLabel($invoice->buyer->idType ?? '')) ?></span>
        <?= $esc($invoice->buyer->id) ?>
      </div>
      <?php endif; ?>
      <?php foreach ($invoice->buyer->identifiers as $gid): ?>
      <div class="di-identifier">
        <span class="di-label"><?= $esc($schemeLabel($gid['idType'])) ?></span>
        <?= $esc($gid['id']) ?>
      </div>
      <?php endforeach; ?>
      <?php foreach ($invoice->buyer->taxRegistrations as $reg): ?>
      <div class="di-identifier">
        <span class="di-label"><?= $esc($schemeLabel($reg['schemeID'])) ?></span>
        <?= $esc($reg['id']) ?>
      </div>
      <?php endforeach; ?>
      <?php if ($invoice->buyerReference): ?>
      <div class="di-identifier">
        <span class="di-label"><?= $labels['ref'] ?></span>
        <?= $esc($invoice->buyerReference) ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

  </section>

  <?php if ($invoice->items): ?>
  <section class="di-items">
    <table class="di-table">
      <thead>
        <tr>
          <th class="di-col--name"><?= $labels['description'] ?></th>
          <th class="di-col--qty"><?= $labels['qty'] ?></th>
          <th class="di-col--unit"><?= $labels['unit'] ?></th>
          <th class="di-col--price"><?= $labels['unit_price'] ?></th>
          <th class="di-col--tax"><?= $labels['vat_pct'] ?></th>
          <th class="di-col--total"><?= $labels['line_total'] ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($invoice->items as $item): ?>
        <tr>
          <td>
            <?= $esc($item->name) ?>
            <?php if ($item->description): ?>
            <small><?= $esc($item->description) ?></small>
            <?php endif; ?>
          </td>
          <td class="di-col--qty"><?= $esc((string) $item->quantity) ?></td>
          <td class="di-col--unit"><?= $esc($item->unit) ?></td>
          <td class="di-col--price"><?= $fmt($item->price) ?></td>
          <td class="di-col--tax"><?= $fmt($item->taxRate) ?>%</td>
          <td class="di-col--total"><?= $fmt(round($item->price * $item->quantity, 2)) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>
  <?php endif; ?>

  <section class="di-totals">
    <?php if ($invoice->taxBasisTotal !== null): ?>
    <div class="di-total-row"><span><?= $labels['tax_basis'] ?></span><span><?= $fmt($invoice->taxBasisTotal, $cur) ?></span></div>
    <?php endif; ?>
    <?php if ($invoice->taxTotal !== null): ?>
    <div class="di-total-row"><span><?= $labels['vat'] ?></span><span><?= $fmt($invoice->taxTotal, $cur) ?></span></div>
    <?php endif; ?>
    <?php if ($invoice->grandTotal !== null): ?>
    <div class="di-total-row di-total-row--grand"><span><?= $labels['total'] ?></span><span><?= $fmt($invoice->grandTotal, $cur) ?></span></div>
    <?php endif; ?>
    <?php if ($invoice->duePayable !== null && $invoice->duePayable !== $invoice->grandTotal): ?>
    <div class="di-total-row"><span><?= $labels['due_payable'] ?></span><span><?= $fmt($invoice->duePayable, $cur) ?></span></div>
    <?php endif; ?>
  </section>

  <?php if ($invoice->paymentMeans): ?>
  <section class="di-payment">
    <h3><?= $labels['payment'] ?></h3>
    <?php foreach ($invoice->paymentMeans as $pm): ?>
    <div class="di-payment-mean">
      <?php if ($pm->iban): ?><div><span class="di-label"><?= $labels['iban'] ?></span> <?= $esc($pm->iban) ?></div><?php endif; ?>
      <?php if ($pm->bic): ?><div><span class="di-label"><?= $labels['bic'] ?></span> <?= $esc($pm->bic) ?></div><?php endif; ?>
      <?php if ($pm->accountName): ?><div><span class="di-label"><?= $labels['account'] ?></span> <?= $esc($pm->accountName) ?></div><?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php if ($invoice->paymentTermsDescription): ?>
    <p class="di-payment-terms"><?= $esc($invoice->paymentTermsDescription) ?></p>
    <?php endif; ?>
  </section>
  <?php endif; ?>

</div>
