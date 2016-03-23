<?php

$PluginInfo['conversationPublication'] = array(
    'Name' => 'Conversation Publication',
    'Description' => 'Users with appropriate rights can convert a conversation into a discussion.',
    'Version' => '0.1',
    'RequiredApplications' => array(
        'Vanilla' => '>=2.2',
        'Conversations' => '>=2.2'
    ),
    'MobileFriendly' => true,
    'HasLocale' => true,
    'SettingsUrl' => 'settings/conversationpublication',
    'SettingsPermission' => array(
        'Garden.Settings.Manage',
        'Garden.Community.Manage'
    ),
    'RegisterPermissions' => array('ConversationPublication.Manage'),
    'Author' => 'Robin Jurinka',
    'AuthorUrl' => 'http://vanillaforums.org/profile/R_J',
    'License' => 'MIT'
);

/**
 * Plugin to allow transformation of a conversation into a discussion.
 *
 */
class ConversationPublicationPlugin extends Gdn_Plugin {
    public $Participants = array(); // array of users for which discussion has to be cleared and who has to be notified

    /**
     * Prefill config settings with sane values.
     *
     * @return void.
     */
    public function setup() {
        // Whether the conversation starter is allowed to start the publishing
        // process.
        touchConfig('ConversationPublication.PublicationMessage', 'This discussion has been created from a conversation.');

        // Whether the every conversation participant is allowed to start the
        // publishing process.
        touchConfig(
            'ConversationPublication.PublicationMessageUserID',
            Gdn::userModel()->getSystemUserID()
        );

        $this->structure();
    }

    /**
     * Add activity type.
     *
     * @return void.
     */
    public function structure() {
        $activityModel = new activityModel();
        $activityModel->DefineType(
            'ConversationPublication',
            array(
                'AllowComments' => true,
                'ShowIcon' => true,
                // %1 = ActivityName ( = acting users name)
                // %2 = ActivityName Possessive
                // %3 = RegardingName ( = affected users name)
                // %4 = RegardingName Possessive
                // %5 = Link to RegardingName's Wall
                // %6 = his/her
                // %7 = he/she
                // %8 = RouteCode & Route
                'ProfileHeadline' => '%1$s wants to publish a %8$s.',
                'FullHeadline' => '%1$s wants to publish a %8$s.',
                'RouteCode' => 'conversation',
                'Notify' => '1',
                'Public' => '0'
            )
        );
    }

    /**
     * Settings screen.
     *
     * @param SettingsController $sender The calling controller.
     * @return void.
     */
    public function settingsController_conversationPublication_create($sender) {
        echo 'config settings<br>';
        echo 'hints for role configuration (which permission is needed for what';
    }

    /**
     * Add the module to messages.
     *
     * @param messagesController $sender The calling controller.
     * @return void.
     */
    public function messagesController_render_before($sender) {
        // Check if user has permission to publish conversation.
        if (!checkPermission('ConversationPublication.Manage')) {
            return;
        }

        // Load and add module.
        $conversationPublicationModule = new conversationPublicationModule($sender);
        $conversationPublicationModule->setData(
            'ConversationID',
            $sender->Conversation->ConversationID
        );
        $sender->addModule($conversationPublicationModule);
    }

    /**
     * Shows form for choosing the category in which to publish the conversation.
     *
     * @param pluginController $sender The calling controller.
     * @param mixed $args Slug parameter.
     * @return void.
     */
    public function pluginController_conversationPublication_create($sender, $args) {
        checkPermission('ConversationPublication.Manage');

        // Get the conversation by its ID.
        $conversationModel = new conversationModel();
        $conversation = $conversationModel->getID((int)$args[0]);

        // Prepare the form.
        $sender->Form = new Gdn_Form();
        // Get the form values to check.
        $formValues = $sender->Form->formValues();
        if (
            $sender->Form->authenticatedPostBack() &&
            isset($formValues[t('Publish!')])
        ) {
            $discussionID = $this->_conversation2Discussion(
                $conversation,
                $formValues
            );
            if (!$discussionID) {
                // Toast errors
                return;
            }
            // Delete conversation!
            // conversationModel->clear($conversationID, $userID)
            // notify participants
            // Reroute to new discussion.
            // redirect(url('/discussion'.$discussionID));

        }

        $sender->setData('DiscussionName', $conversation->Subject);

        $sender->render($this->getView('conversationpublication.php'));
    }

    private function _conversation2Discussion($conversation, $formValues) {
        checkPermission('ConversationPublication.Manage');
        try {
            // Get all conversation messages.
            $conversationMessageModel = new conversationMessageModel();
            $conversationMessages = $conversationMessageModel
                ->get(
                    $conversation->ConversationID,
                    $conversation->InsertUserID
                )
                ->resultArray();
// TODO fill $this->_Participants = array of users for which discussion has to be cleared and who has to be notified
            // Create Discussion.
            $discussionModel = new discussionModel();
            $discussion = array(
                'CategoryID' => $formValues['CategoryID'],
                'Name' => $formValues['DiscussionName'],
                'Body' => $conversationMessages[0]['Body'],
                'Format' => $conversationMessages[0]['Format'],
                'DraftID' => '0',
                'InsertUserID' => $conversation->InsertUserID,
                'InsertIPAddress' => $conversation->InsertIPAddress,
                'DateInserted'  => $conversation->DateInserted,
                'ForeignID' => $conversation->ConversationID,
                'TransientKey' => Gdn::UserModel()->setTransientKey($conversation->InsertUserID)
            );
            $discussionID = $discussionModel->save($discussion);
            if ((int)$discussionID <= 0) {
                throw new Exception(t('Discussion could not be created.'));
            }

            // First Message is new discussion, so it can be deleted.
            unset($conversationMessages[0]);

            // Add a closing comment to explain users where this discussion
            // comes from.
            $insertUserID = c(
                'ConversationPublication.PublicationMessageUserID',
                Gdn::userModel()->getSystemUserID()
            );
            $comment = array(
                'DiscussionID' => $discussionID,
                'InsertUserID' => $insertUserID,
                'Body' => c('ConversationPublication.PublicationMessage', t('ConversationPublication.PublicationMessage')),
                'Format' => 'Html',
                'DraftID' => '0',
                'TransientKey' => Gdn::userModel()->setTransientKey($insertUserID)
            );
            $conversationMessages[] = $comment;

            // Create commentsfrom the rest of the messages.
            $commentModel = new commentModel();
            foreach ($conversationMessages as $message) {
                $comment = array(
                    'DiscussionID' => $discussionID,
                    'InsertUserID' => $message['InsertUserID'],
                    'Body' => $message['Body'],
                    'Format' => $message['Format'],
                    'DraftID' => '0',
                    'DateInserted' => $message['DateInserted'],
                    'InsertIPAddress' => $message['InsertIPAddress'],
                    'TransientKey' => Gdn::userModel()->setTransientKey($message['InsertUserID'])
                );
                $commentID = $commentModel->save($comment);
                if ((int)$commentID <= 0) {
                    // Conversion wasn't successful so delete discussion.
                    $discussionModel->delete(
                        $discussionID,
                        array('Log' => false)
                    );
                    // TODO: make translatable with sprintf.
                    throw new Exception('Message '.$message['MessageID'].' could not be saved as a comment.');
                }
            }
            return $discussionID;
        } catch (Exception $e) {
            return false;
        }
    }
}
