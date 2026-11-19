<?php

// Momentum harness: CKEditor adopts (duplicates) any entry its HTML points at when the owner is saved, so a
// `<craft-entry>` naming an entry the field doesn't own can only come from content written around Craft
// (imports, migrations, direct DB edits). Simulate that: point the kitchen sink's last <craft-entry> at its
// first Matrix block, straight in the stored content JSON.
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Support\Facades\Fields;
use Illuminate\Support\Facades\DB;

$sink = Entry::find()->section('momentumKitchen')->slug('momentum-kitchen-sink')->one();
$block = Entry::find()->ownerId($sink->id)->fieldId(Fields::getFieldByHandle('momentumBlocks')->id)->type('momentumText')->one();
preg_match_all('/data-entry-id="(\d+)"/', $sink->getFieldValue('momentumBody')->getRawContent(), $m);
$last = end($m[1]);
$row = DB::table('elements_sites')->where('elementId', $sink->id)->where('siteId', $sink->siteId)->first();
$content = json_decode($row->content, true);
array_walk_recursive($content, function (&$v) use ($last, $block) {
    if (is_string($v)) {
        $v = str_replace('data-entry-id="'.$last.'"', 'data-entry-id="'.$block->id.'"', $v);
    }
});
DB::table('elements_sites')->where('id', $row->id)->update(['content' => json_encode($content)]);
echo json_encode(['block' => $block->id]);
