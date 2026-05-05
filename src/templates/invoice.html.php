<?php
/**
 * Default invoice template.
 *
 * Variables injected by InvoiceRenderer::render():
 *   @var \DigitalInvoice\InvoiceData $invoice
 *   @var string                      $cur      currency code
 *   @var \Closure                    $esc      HTML-safe string
 *   @var \Closure                    $fmt      formatted monetary amount
 *   @var \Closure                    $date     formatted DateTime or '—'
 */
?>
<div class="di-invoice">

  <header class="di-header">
    <div class="di-header__meta">
      <span class="di-label">Invoice</span>
      <span class="di-invoice-id"><?= $esc($invoice->invoiceId) ?></span>
    </div>
    <div class="di-header__dates">
      <div><span class="di-label">Issue date</span> <?= $date($invoice->issueDate) ?></div>
      <?php if ($invoice->deliveryDate): ?>
      <div><span class="di-label">Delivery date</span> <?= $date($invoice->deliveryDate) ?></div>
      <?php endif; ?>
      <?php if ($invoice->dueDate): ?>
      <div><span class="di-label">Due date</span> <?= $date($invoice->dueDate) ?></div>
      <?php endif; ?>
    </div>
    <div class="di-header__misc">
      <div><span class="di-label">Currency</span> <?= $esc($invoice->currency) ?></div>
      <?php if ($invoice->invoiceType && $invoice->invoiceType !== '380'): ?>
      <div><span class="di-label">Type</span> <?= $esc($invoice->invoiceType) ?></div>
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
      <h3 class="di-party__role">Seller</h3>
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
      <?php foreach ($invoice->seller->taxRegistrations as $reg): ?>
      <div class="di-tax-reg"><?= $esc($reg['schemeID']) ?>: <?= $esc($reg['id']) ?></div>
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
      <h3 class="di-party__role">Buyer</h3>
      <strong><?= $esc($invoice->buyer->name) ?></strong>
      <?php if ($invoice->buyer->address): $a = $invoice->buyer->address; ?>
      <address>
        <?= $esc($a->lineOne) ?><br>
        <?php if ($a->lineTwo): ?><?= $esc($a->lineTwo) ?><br><?php endif; ?>
        <?= $esc($a->postCode) ?> <?= $esc($a->city) ?><br>
        <?= $esc($a->countryCode) ?>
      </address>
      <?php endif; ?>
      <?php if ($invoice->buyerReference): ?>
      <div class="di-buyer-ref"><span class="di-label">Ref</span> <?= $esc($invoice->buyerReference) ?></div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

  </section>

  <?php if ($invoice->items): ?>
  <section class="di-items">
    <table class="di-table">
      <thead>
        <tr>
          <th class="di-col--name">Description</th>
          <th class="di-col--qty">Qty</th>
          <th class="di-col--unit">Unit</th>
          <th class="di-col--price">Unit price</th>
          <th class="di-col--tax">VAT %</th>
          <th class="di-col--total">Line total</th>
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
    <div class="di-total-row"><span>Tax basis</span><span><?= $fmt($invoice->taxBasisTotal, $cur) ?></span></div>
    <?php endif; ?>
    <?php if ($invoice->taxTotal !== null): ?>
    <div class="di-total-row"><span>VAT</span><span><?= $fmt($invoice->taxTotal, $cur) ?></span></div>
    <?php endif; ?>
    <?php if ($invoice->grandTotal !== null): ?>
    <div class="di-total-row di-total-row--grand"><span>Total</span><span><?= $fmt($invoice->grandTotal, $cur) ?></span></div>
    <?php endif; ?>
    <?php if ($invoice->duePayable !== null && $invoice->duePayable !== $invoice->grandTotal): ?>
    <div class="di-total-row"><span>Due payable</span><span><?= $fmt($invoice->duePayable, $cur) ?></span></div>
    <?php endif; ?>
  </section>

  <?php if ($invoice->paymentMeans): ?>
  <section class="di-payment">
    <h3>Payment</h3>
    <?php foreach ($invoice->paymentMeans as $pm): ?>
    <div class="di-payment-mean">
      <?php if ($pm->iban): ?><div><span class="di-label">IBAN</span> <?= $esc($pm->iban) ?></div><?php endif; ?>
      <?php if ($pm->bic): ?><div><span class="di-label">BIC</span> <?= $esc($pm->bic) ?></div><?php endif; ?>
      <?php if ($pm->accountName): ?><div><span class="di-label">Account</span> <?= $esc($pm->accountName) ?></div><?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php if ($invoice->paymentTermsDescription): ?>
    <p class="di-payment-terms"><?= $esc($invoice->paymentTermsDescription) ?></p>
    <?php endif; ?>
  </section>
  <?php endif; ?>

</div>
