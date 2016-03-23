<h4><?= t('Publish this conversation as a discussion') ?></h4>
<div class="Warning"><?= t('This will convert this <strong>private</strong> conversation into a <strong>public</strong> discussion! Be sure there is no confidential info that will be revealed.') ?></div>
<?= $this->Form->open() ?>
<?= $this->Form->errors() ?>
<p>
<?= $this->Form->label('Please choose Category', 'CategoryID') ?>
<?= $this->Form->categoryDropDown() ?>
</p>
<p>
<?= $this->Form->label('Discussion title', 'DiscussionName') ?>
<?= $this->Form->textBox('DiscussionName', array('Class' => 'InputBox', 'Value' => $this->data('DiscussionName'))) ?>
</p>
<p><?= $this->Form->button('Publish!', array('Class' => 'Button SmallButton Danger ConversationPublication')) ?></p>
<?= $this->Form->close() ?>
