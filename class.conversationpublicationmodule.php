<?php

/**
 * Shows a button in conversation.
 *
 * The button opens a form where a user can choose the category into which the
 * conversation should be inserted as a discussion.
 */
class ConversationPublicationModule extends Gdn_Module {
    /**
     * Needed function asset target.
     *
     * @return string Default destination for the module.
     */
    public function assetTarget() {
        return 'Panel';
    }

    /**
     * Markup that the module echos.
     *
     * @return void.
     */
    public function toString() {
        echo anchor(
            t('Publish as Discussion'),
            'plugin/conversationpublication/'.$this->data('ConversationID'),
            'Button BigButton Danger ConversationPublication Popup Hijack'
        );
    }
}
