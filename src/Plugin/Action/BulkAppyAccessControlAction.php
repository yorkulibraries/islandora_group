<?php

namespace Drupal\fits\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\advancedqueue\Entity\Queue;
use Drupal\advancedqueue\Job;

/**
 * Provides a 'FitsAction' action.
 *
 * @Action(
 *  id = "bulk_appy_access_control_action",
 *  label = @Translation("Bulk apply access control with Groups"),
 *  type = "node",
 *  category = @Translation("Custom")
 * )
 */
class BulkAppyAccessControlAction extends ActionBase {

    /**
     * Implements access()
     */
    public function access($file, AccountInterface $account = NULL, $return_as_object = FALSE) {
        /** @var \Drupal\node\NodeInterface $node */
        $access = $node->access('update', $account, TRUE)
            ->andIf($node->title->access('edit', $account, TRUE));
        return $return_as_object ? $access : $access->isAllowed();
    }

    /**
     * Implements execute().
     */
    public function execute($file = NULL) {

    }

}