<?php

final class ICFlowOpenCommentsField extends ICFlowField {

  private $workspace_transaction_phids;
  private $workspace_comments;

  public function getFieldKey() {
    return 'open-comments';
  }

  public function getSummary() {
    return pht('Number of open comments in the revision corresponding to the HEAD of the branch, if any.');
  }

  public function isDefaultField() {
    return false;
  }

  protected function getFutures(ICFlowWorkspace $workspace) {
    $features = $workspace->getFeatures();

    $rev_phids = array_unique(array_filter(mpull($features, 'getRevisionPHID')));
    if (!$rev_phids) {
      return [];
    }

    $transaction_search_futures = [];
    foreach ($rev_phids as $rev_phid) {
      $transaction_search_futures[$rev_phid] = $workspace->getConduit()->callMethod('transaction.search', [
        'objectIdentifier' => $rev_phid,
      ]);
    }

    $this->workspace_transaction_phids = [];
    $this->workspace_comments = [];
    $all_transaction_phids = [];
    foreach (new FutureIterator($transaction_search_futures) as $rev_phid => $future) {
      $rev_transactions = idx($future->resolve(), 'data');
      $rev_comments = $this->createCommentsFromTransactions($rev_transactions);
      $rev_transaction_phids = ipull($rev_transactions, 'phid');
      $this->workspace_comments[$rev_phid] = $rev_comments;
      $this->workspace_transaction_phids[$rev_phid] = $rev_transaction_phids;
      $all_transaction_phids = array_mergev([$all_transaction_phids, $rev_transaction_phids]);
    }


    $transactions_query_future = $workspace->getConduit()->callMethod('transactions.query', [
      'phids' => $all_transaction_phids,
    ]);

    return ['transactions_query' => $transactions_query_future];
  }

  protected function renderValues(array $values) {
    $open_comments = idx($values, 'open-comments');
    if ($open_comments == 0) {
      return null;
    }
    return pht("*%s", $open_comments);
  }

  public function getValues(ICFlowFeature $feature) {
    if ($rev_phid = $feature->getRevisionPHID()) {
      $queried_workspace_transactions = $this->getFutureResult('transactions_query', []);
      $queried_workspace_transactions = ipull($queried_workspace_transactions, null, 'phid');

      $rev_transaction_phids = idx($this->workspace_transaction_phids, $rev_phid,[]);
      $queried_rev_transactions = $this->getQueriedRevisionTransactions($rev_transaction_phids,
                                                                        $queried_workspace_transactions);
      $rev_comments = idx($this->workspace_comments, $rev_phid, []);
      $rev_comments = $this->updateCommentsFromTransactions($queried_rev_transactions, $rev_comments);

      $open_comments = $this->countOpenComments($rev_comments);

      return array('open-comments' => $open_comments);
    }
    return null;
  }

  private function getQueriedRevisionTransactions($rev_transaction_phids, $queried_workspace_transactions) {
    return array_select_keys($queried_workspace_transactions, $rev_transaction_phids);
  }

  private function countOpenComments($comment_list) {
    return idx(array_count_values($comment_list), 'open', 0);
  }

  private function createCommentsFromTransactions($rev_transactions) {
    $comment_list = [];
    foreach ($rev_transactions as $transaction) {
      if (idx($transaction, 'type') == 'inline') {
        $transaction_comments = $this->createCommentsFromSingleTransaction($transaction);
        $comment_list = array_merge($comment_list, $transaction_comments);
      }
    }
    return $comment_list;
  }

  private function createCommentsFromSingleTransaction($transaction) {
    $comment_list = [];
    foreach (idx($transaction, 'comments', []) as $comment) {
      $comment_phid = idx($comment, 'phid');
      $comment_list[$comment_phid] = 'open';
    }
    return $comment_list;
  }

  private function updateCommentsFromTransactions($rev_transactions, $comment_list) {
    foreach (array_reverse($rev_transactions) as $transaction) { # transactions are in most recent order from Conduit
      if(idx($transaction, 'transactionType') == 'core:inlinestate') {
        $comment_list = $this->updateCommentsFromSingleTransaction($transaction, $comment_list);
      }
    }
    return $comment_list;
  }

  private function updateCommentsFromSingleTransaction($transaction, $comment_list) {
    foreach (idx($transaction, 'newValue', []) as $comment_phid => $new_value) {
      if($new_value == 'done') {
        $comment_list[$comment_phid] = 'done';
      } else {
        $comment_list[$comment_phid] = 'open';
      }
    }
    return $comment_list;
  }
}
