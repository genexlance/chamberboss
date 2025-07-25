<?php

namespace Chamberboss\Admin;

use Chamberboss\Core\BaseClass;

class TransactionsPage extends BaseClass
{
    protected function init() {
        // Initialization logic for TransactionsPage
    }

    public function render()
    {
        ?>
        <div class="wrap">
            <h1><?php _e('Transactions', 'chamberboss'); ?></h1>
            <p><?php _e('This is the transactions management page.', 'chamberboss'); ?></p>
        </div>
        <?php
    }
} 