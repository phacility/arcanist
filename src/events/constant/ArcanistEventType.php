<?php

final class ArcanistEventType extends PhutilEventType {

  const TYPE_COMMIT_WILLCOMMITSVN   = 'commit.willCommitSVN';

  const TYPE_DIFF_DIDCOLLECTCHANGES = 'diff.didCollectChanges';
  const TYPE_DIFF_WILLBUILDMESSAGE  = 'diff.willBuildMessage';
  const TYPE_DIFF_DIDBUILDMESSAGE   = 'diff.didBuildMessage';
  const TYPE_DIFF_WASCREATED        = 'diff.wasCreated';

  const TYPE_REVISION_WILLCREATEREVISION = 'revision.willCreateRevision';

  const TYPE_LAND_WILLPUSHREVISION  = 'land.willPushRevision';

}
