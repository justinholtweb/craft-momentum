<?php

// Momentum harness fixtures (idempotent). Everything is prefixed `momentum`; cleanup.php removes it.
//   volume momentumImages (public disk, momentum/ subpath) + one PNG, transform momentumThumb
//   fields momentumBody (CKEditor with nested entries), momentumImage (Assets), momentumBlocks (Matrix),
//          momentumLink (Link), momentumTone (Dropdown), momentumTags (Checkboxes), momentumFeatured (Lightswitch),
//          momentumPrice (Money), momentumAuthor (Users), momentumSecret (PlainText), momentumHeading (PlainText),
//          momentumRelated (Entries), momentumAddress (Addresses)
//   entry types momentumKitchen, momentumText (nested), momentumGallery (nested)
//   section momentumKitchen (channel, uri momentum-kitchen/{slug}, template inertia:Pages/Kitchen)
//   entries momentum-kitchen-sink (everything), momentum-related-a/b, user momentumauthor
// Run with: ddev artisan tinker --execute "require 'storage/momentum-fixtures.php';"

use CraftCms\Ckeditor\Field;
use CraftCms\Cms\Asset\Data\Volume;
use CraftCms\Cms\Asset\Elements\Asset;
use CraftCms\Cms\Asset\Validation\AssetRules;
use CraftCms\Cms\Entry\Data\EntryType;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Field\Addresses;
use CraftCms\Cms\Field\Assets;
use CraftCms\Cms\Field\Checkboxes;
use CraftCms\Cms\Field\Dropdown;
use CraftCms\Cms\Field\Entries;
use CraftCms\Cms\Field\Lightswitch;
use CraftCms\Cms\Field\Link;
use CraftCms\Cms\Field\Matrix;
use CraftCms\Cms\Field\Money;
use CraftCms\Cms\Field\PlainText;
use CraftCms\Cms\Field\Users;
use CraftCms\Cms\FieldLayout\FieldLayout;
use CraftCms\Cms\FieldLayout\LayoutElements\CustomField;
use CraftCms\Cms\FieldLayout\LayoutElements\TitleField;
use CraftCms\Cms\Image\Data\ImageTransform;
use CraftCms\Cms\Section\Data\Section;
use CraftCms\Cms\Section\Data\SectionSiteSettings;
use CraftCms\Cms\Section\Enums\SectionType;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\EntryTypes;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Support\Facades\Folders;
use CraftCms\Cms\Support\Facades\ImageTransforms;
use CraftCms\Cms\Support\Facades\Sections;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Facades\Volumes;
use CraftCms\Cms\User\Elements\User;

$fail = fn (string $what, $model) => throw new RuntimeException($what.': '.json_encode(method_exists($model, 'errors') ? $model->errors()->all() : $model));

// ---- Volume, image, transform ----
$volume = Volumes::getVolumeByHandle('momentumImages');
if (! $volume) {
    $volume = new Volume(['name' => 'Momentum Images', 'handle' => 'momentumImages', 'hasUrls' => true]);
    $volume->fsHandle = 'public';
    $volume->subpath = 'momentum';
    Volumes::saveVolume($volume) || $fail('volume', $volume);
}

if (! ImageTransforms::getTransformByHandle('momentumThumb')) {
    $t = new ImageTransform(['name' => 'Momentum Thumb', 'handle' => 'momentumThumb', 'width' => 64, 'height' => 64, 'mode' => 'crop']);
    ImageTransforms::saveTransform($t) || $fail('transform', $t);
}

$asset = Asset::find()->volume('momentumImages')->filename('momentum-pixel.png')->one();
if (! $asset) {
    $img = imagecreatetruecolor(120, 80);
    imagefill($img, 0, 0, imagecolorallocate($img, 230, 120, 40));
    $tmp = sys_get_temp_dir().'/momentum-pixel.png';
    imagepng($img, $tmp);
    $asset = new Asset;
    $asset->tempFilePath = $tmp;
    $asset->filename = 'momentum-pixel.png';
    $asset->newFolderId = Folders::getRootFolderByVolumeId($volume->id)->id;
    $asset->volumeId = $volume->id;
    $asset->title = 'Momentum Pixel';
    $asset->ruleset->useScenario(AssetRules::SCENARIO_CREATE);
    Elements::saveElement($asset) || $fail('asset', $asset);
}
$asset = Asset::find()->id($asset->id)->one();
if ($asset->alt !== 'An orange rectangle') {
    $asset->alt = 'An orange rectangle';
    Elements::saveElement($asset) || $fail('asset alt', $asset);
}

// ---- Fields ----
$mk = function (string $type, string $handle, array $extra = []) use ($fail) {
    $f = Fields::getFieldByHandle($handle);
    if ($f) {
        return $f;
    }
    $f = Fields::createField(['type' => $type, 'name' => ucfirst(preg_replace('/([A-Z])/', ' $1', $handle)), 'handle' => $handle] + $extra);
    Fields::saveField($f) || $fail("field {$handle}", $f);

    return $f;
};
$layout = fn (array $fields, bool $title = true) => FieldLayout::createFromConfig(['type' => Entry::class, 'tabs' => [['name' => 'Content', 'elements' => [
    ...($title ? [['type' => TitleField::class]] : []),
    ...array_map(fn ($f) => ['type' => CustomField::class, 'fieldUid' => $f->uid], $fields),
]]]]);
// Layouts are only set on creation: a new layout gets new element UIDs, and content is keyed by them.
$entryType = function (string $handle, array $fields) use ($layout, $fail) {
    if ($t = EntryTypes::getEntryTypeByHandle($handle)) {
        return $t;
    }
    $t = new EntryType(['name' => ucfirst($handle), 'handle' => $handle]);
    $t->setFieldLayout($layout($fields));
    EntryTypes::saveEntryType($t) || $fail("entry type {$handle}", $t);

    return EntryTypes::getEntryTypeByHandle($handle);
};

$heading = $mk(PlainText::class, 'momentumHeading');
$secret = $mk(PlainText::class, 'momentumSecret');
$image = $mk(Assets::class, 'momentumImage', ['sources' => ['volume:'.$volume->uid]]);
$related = $mk(Entries::class, 'momentumRelated');
$author = $mk(Users::class, 'momentumAuthor');
$link = $mk(Link::class, 'momentumLink', ['types' => ['url', 'entry'], 'showLabelField' => true]);
$tone = $mk(Dropdown::class, 'momentumTone', ['options' => [
    ['label' => 'Warm', 'value' => 'warm', 'default' => true], ['label' => 'Cool', 'value' => 'cool'],
]]);
$tags = $mk(Checkboxes::class, 'momentumTags', ['options' => [
    ['label' => 'Red', 'value' => 'red'], ['label' => 'Green', 'value' => 'green'], ['label' => 'Blue', 'value' => 'blue'],
]]);
$featured = $mk(Lightswitch::class, 'momentumFeatured');
$price = $mk(Money::class, 'momentumPrice', ['currency' => 'USD']);
$address = $mk(Addresses::class, 'momentumAddress');

$text = $entryType('momentumText', [$heading, $secret]);
$gallery = $entryType('momentumGallery', [$image, $related]);

$blocks = Fields::getFieldByHandle('momentumBlocks');
if (! $blocks) {
    $blocks = Fields::createField(['type' => Matrix::class, 'name' => 'Momentum Blocks', 'handle' => 'momentumBlocks']);
    $blocks->setEntryTypes([$text, $gallery]);
    Fields::saveField($blocks) || $fail('matrix', $blocks);
}

$body = Fields::getFieldByHandle('momentumBody');
if (! $body && class_exists(Field::class)) {
    // `createEntry` in the toolbar is what lets `<craft-entry>` through CKEditor's sanitizer.
    $body = Fields::createField(['type' => Field::class, 'name' => 'Momentum Body', 'handle' => 'momentumBody',
        'toolbar' => ['heading', '|', 'bold', 'italic', 'link', 'createEntry']]);
    $body->setEntryTypes([$text]);
    Fields::saveField($body) || $fail('ckeditor', $body);
}

$kitchenFields = array_values(array_filter([$heading, $secret, $body, $image, $blocks, $link, $tone, $tags, $featured, $price, $author, $related, $address]));
$kitchen = $entryType('momentumKitchen', $kitchenFields);

// ---- Section ----
$section = Sections::getSectionByHandle('momentumKitchen');
if (! $section) {
    $section = new Section(['name' => 'Momentum Kitchen', 'handle' => 'momentumKitchen', 'type' => SectionType::Channel]);
    $siteSettings = [];
    // The primary site only: other sessions add and remove their own sites on the shared harness.
    foreach ([Sites::getPrimarySite()] as $site) {
        $s = new SectionSiteSettings;
        $s->siteId = $site->id;
        $s->hasUrls = true;
        $s->uriFormat = 'momentum-kitchen/{slug}';
        $s->template = 'inertia:Pages/Kitchen';
        $s->enabledByDefault = true;
        $siteSettings[$site->id] = $s;
    }
    $section->setSiteSettings($siteSettings);
    $section->setEntryTypes([$kitchen]);
    Sections::saveSection($section) || $fail('section', $section);
    $section = Sections::getSectionByHandle('momentumKitchen');
}

// ---- User, entries ----
$user = User::find()->username('momentumauthor')->status(null)->one() ?? new User;
$user->username = 'momentumauthor';
$user->email = 'momentum-author@momentum.test';
$user->fullName = 'Ada Momentum';
$user->active = true;
$user->pending = false;
Elements::saveElement($user) || $fail('user', $user);

$entry = function (string $slug, string $title, array $values = []) use ($section, $kitchen, $fail) {
    $e = Entry::find()->section('momentumKitchen')->slug($slug)->status(null)->one() ?? new Entry([
        'sectionId' => $section->id, 'typeId' => $kitchen->id,
    ]);
    $e->slug = $slug;
    $e->title = $title;
    $e->enabled = true;
    $e->setFieldValues($values);
    Elements::saveElement($e) || $fail("entry {$slug}", $e);

    return $e;
};

$b = $entry('momentum-related-b', 'Related B', ['momentumHeading' => 'Two hops away']);
$a = $entry('momentum-related-a', 'Related A', ['momentumHeading' => 'One hop away', 'momentumRelated' => [$b->id], 'momentumImage' => [$asset->id]]);

$sink = $entry('momentum-kitchen-sink', 'Kitchen Sink', [
    'momentumHeading' => 'Everything at once',
    'momentumSecret' => 'KITCHEN-SECRET',
    'momentumImage' => [$asset->id],
    'momentumLink' => ['type' => 'url', 'value' => 'https://example.com/momentum', 'label' => 'Example'],
    'momentumTone' => 'cool',
    'momentumTags' => ['red', 'blue'],
    'momentumFeatured' => true,
    'momentumPrice' => ['value' => '12.50', 'currency' => 'USD'],
    'momentumAuthor' => [$user->id],
    'momentumRelated' => [$a->id],
    'momentumBlocks' => [
        'new1' => ['type' => 'momentumText', 'title' => 'Block one', 'fields' => ['momentumHeading' => 'First block', 'momentumSecret' => 'BLOCK-SECRET']],
        'new2' => ['type' => 'momentumGallery', 'title' => 'Block two', 'fields' => ['momentumImage' => [$asset->id], 'momentumRelated' => [$a->id]]],
        'new3' => ['type' => 'momentumGallery', 'title' => 'Block three', 'fields' => ['momentumImage' => [$asset->id], 'momentumRelated' => [$b->id]]],
        'new4' => ['type' => 'momentumGallery', 'title' => 'Block four', 'fields' => ['momentumImage' => [$asset->id], 'momentumRelated' => [$a->id, $b->id]]],
    ],
]);

// Matrix values are only set on the first run; later runs keep the existing blocks.
// CKEditor body with one nested entry between two paragraphs.
if ($body) {
    $nested = Entry::find()->ownerId($sink->id)->fieldId($body->id)->status(null)->one();
    if (! $nested) {
        $nested = new Entry(['fieldId' => $body->id, 'ownerId' => $sink->id, 'typeId' => $text->id]);
        $nested->title = 'Inline block';
        $nested->setFieldValues(['momentumHeading' => 'Nested in CKEditor']);
        Elements::saveElement($nested) || $fail('ckeditor nested entry', $nested);
    }
    $sink->setFieldValue('momentumBody', '<p>Intro <strong>bold</strong></p><craft-entry data-entry-id="'.$nested->id.'"></craft-entry><p>Outro</p>');
    Elements::saveElement($sink) || $fail('sink body', $sink);
}

// Same shape, three times the blocks: an eager-loaded page must cost the same number of queries.
$many = [];
foreach (range(1, 9) as $i) {
    $many["new{$i}"] = ['type' => 'momentumGallery', 'title' => "Gallery {$i}", 'fields' => ['momentumImage' => [$asset->id], 'momentumRelated' => [$i % 2 ? $a->id : $b->id]]];
}
if (! Entry::find()->section('momentumKitchen')->slug('momentum-many-blocks')->status(null)->exists()) {
    $entry('momentum-many-blocks', 'Many Blocks', ['momentumRelated' => [$a->id], 'momentumBlocks' => $many]);
}

echo json_encode(['sink' => $sink->id, 'a' => $a->id, 'b' => $b->id, 'asset' => $asset->id, 'user' => $user->id]);
