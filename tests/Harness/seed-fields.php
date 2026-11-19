<?php

use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Field\Entries;
use CraftCms\Cms\Field\PlainText;
use CraftCms\Cms\FieldLayout\FieldLayout;
use CraftCms\Cms\FieldLayout\LayoutElements\CustomField;
use CraftCms\Cms\FieldLayout\LayoutElements\TitleField;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\EntryTypes;
use CraftCms\Cms\Support\Facades\Fields;

$mk = function ($type, $name, $handle, $extra = []) {
    $f = Fields::getFieldByHandle($handle) ?? Fields::createField(['type' => $type, 'name' => $name, 'handle' => $handle] + $extra);
    if (! $f->id && ! Fields::saveField($f)) {
        echo "field $handle: ", json_encode($f->getErrors()), "\n";
    }

    return $f;
};
$summary = $mk(PlainText::class, 'Summary', 'summary');
$notes = $mk(PlainText::class, 'Internal Notes', 'internalNotes');
$related = $mk(Entries::class, 'Related', 'related');
$type = EntryTypes::getEntryTypeByHandle('article');
$layout = FieldLayout::createFromConfig(['type' => Entry::class, 'tabs' => [['name' => 'Content', 'elements' => [
    ['type' => TitleField::class],
    ['type' => CustomField::class, 'fieldUid' => $summary->uid],
    ['type' => CustomField::class, 'fieldUid' => $notes->uid],
    ['type' => CustomField::class, 'fieldUid' => $related->uid],
]]]]);
$type->setFieldLayout($layout);
echo 'entry type: ', EntryTypes::saveEntryType($type) ? 'saved' : json_encode($type->getErrors()), "\n";
$a = Entry::find()->slug('hello-momentum-2')->one();
$b = Entry::find()->slug('second-article-2')->one();
$b->title = 'Second Article';
$b->setFieldValue('summary', 'The related one.');
echo 'b: ', Elements::saveElement($b) ? 'ok' : json_encode($b->getErrors()), "\n";
$a->title = 'Hello Momentum';
$a->setFieldValues(['summary' => 'Inertia pages from Craft routing.', 'internalNotes' => 'SECRET-NOTE', 'related' => [$b->id]]);
echo 'a: ', Elements::saveElement($a) ? 'ok' : json_encode($a->getErrors()), "\n";
