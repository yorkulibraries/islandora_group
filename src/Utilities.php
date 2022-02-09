<?php

namespace Drupal\islandora_group;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\node\NodeInterface;
use Drupal\group\Entity\GroupContent;
use Drupal\media\MediaInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Entity\EntityInterface;
/**
 * Helper functions.
 */
class Utilities {

    /**
     * Get Islandora Access terms associated with Groups
     * @return array
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
     */
    public static function getIslandoraAccessTerms() {
        // create the taxonomy term which has the same name as Group Name
        $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree("islandora_access");
        $groups = self::arrange_group_by_name();

        $result = [];
        foreach ($terms as $term) {
            if (in_array($term->name, array_keys($groups))) {
                $group = $groups[$term->name];
                $result[$term->tid] = $term->name . "  <a href='/group/".$group->id()."/permissions' target='_blank'>Configure permissions</a>";
            }
        }
        return $result;
    }

    /**
     * Create a taxonomy term which is the same name with Group
     * @param \Drupal\Core\Entity\EntityInterface $entity
     * @return void
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
     * @throws \Drupal\Core\Entity\EntityStorageException
     */
    public static function sync_associated_taxonomy_with_group(EntityInterface $entity, string $action) {
        if ($entity->getEntityTypeId() !== "group") {
            return;
        }
        $group_type = $entity->bundle();

        // get the Group associated taxonomy vocabulary
        $taxonomy = "islandora_access";

        $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree($taxonomy);

        // create an taxonomy term which has the same name as group name.
        $existedTerm = null;
        foreach ($terms as $term) {
            if ($term->name === $entity->label()) {
                $existedTerm = $term;
                break;
            }
        }
        switch ($action) {
            case "insert":
            case "update":{
                // if no found terms, create new one
                if ($existedTerm == null) {
                    \Drupal\taxonomy\Entity\Term::create([
                        'name' => $entity->label(),
                        'vid' => $taxonomy,
                    ])->save();
                }
                break;
            }
            case "delete":
                {
                    if ($existedTerm != null) {
                        $controller = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
                        $tobedeleted = $controller->loadMultiple([$existedTerm->tid]);
                        $controller->delete($tobedeleted);
                    }
                    break;
                }
            default:
                {
                    break;
                }
        }
    }

    /**
     * @param $node
     * @return bool
     */
    public static function isCollection($node) {
        if ($node->hasField('field_model') ) {
            // Get associated term model
            $term_id = $node->get("field_model")->getValue()[0]['target_id'];
            $term_name = Term::load($term_id)->get('name')->value;

            // if collection, redirect to the Confirm form with selecting children to tag
            if ($term_name === "Collection") {
                return true;

            }
        }
        return false;
    }

    /**
     * Adding nodes to group
     * @param $entity
     * @return void
     * @throws \Drupal\Core\Entity\EntityStorageException
     */
    public static function adding_islandora_object_to_group($entity) {
        // Exit early if it has no access terms
        if (!$entity->hasField('field_access_terms')) {
            return;
        }

        // Arrange groups keyed by their name so we can look them up later.
        $groups_by_name = self::arrange_group_by_name();

        // clear out group relations with islandora_object first
        self::clear_group_relation_by_islandora_object($entity);

        // Get the access terms for the node.
        $node_terms = $entity->get('field_access_terms')->referencedEntities();
        if (empty($node_terms)) {
            // no term, exist
            return;
        }

        // if there is terms in field_access_term
        foreach ($node_terms as $term) {
            if (isset($groups_by_name[$term->label()])) {
                $group = $groups_by_name[$term->label()];
                $group->addContent($entity, 'group_node:' . $entity->bundle());
            }
        }

        // Reindex media since things have changed.
        /*foreach (\Drupal::service('islandora.utils')->getMedia($entity) as $media) {
            //self::adding_media_of_islandora_object_to_group($entity, $media);

            // check if media already has access_term, if no. ... apply the same as node
            $media_terms = $media->get('field_access_terms')->referencedEntities();
            if (count ($media_terms) <= 0){
                //$media->set('field_access_terms', []);
                self::untag_existed_field_access_terms($media);

                // tag media with same terms as islandora_object
                foreach ($node_terms as $term) {
                    if (isset($groups_by_name[$term->label()])) {
                        $media->field_access_terms[] = ['target_id' => $term->id()];
                    }
                }
                $media->save();

                // add media to group
                self::adding_media_only_into_group($media);
            }


        }*/

    }

    /**
     * @param $entity
     * @return void
     */
    public static function untag_existed_field_access_terms($entity) {
        $terms = $entity->get('field_access_terms')->referencedEntities();
        $i = 0;
        foreach ($terms as $term) {
            if ($entity->get("field_access_terms")->get($i) !== null) {
                $entity->get("field_access_terms")->removeItem($i);
            }
            $i++;
        }
        $entity->save();
    }

    /**
     * Tag a media in to Group
     * @param MediaInterface $media
     * @return void
     */
    public static function adding_media_of_islandora_object_to_group($node, $media) {
        // For media is no parted of any islandora_object
        if (empty($node)) {

            // clear group relation from media
            self::clear_group_relation_by_media($media);

            // add media to node
            self::adding_media_only_into_group($media);

            return;
        }

        // For media is parted of an islandora_object
        if (!$node->hasField('field_access_terms')) {
            return;
        }

        // Arrange groups keyed by their name so we can look them up later.
        $groups_by_name  = self::arrange_group_by_name();

        // clear group relations with the media first
        self::clear_group_relation_by_media($media);

        // Get the access terms for the node.
        $terms = $node->get('field_access_terms')->referencedEntities();
        if (empty($terms)) {
            // no term, exit;
            return;
        }

        if (!$media->hasField('field_access_terms')) {
            return;
        }
        $media->set('field_access_terms', []);

        // if there is terms, loop through and add media group
        foreach ($terms as $term) {
            if (isset($groups_by_name[$term->label()])) {
                self::print_log("tagging and add media to group");
                $group = $groups_by_name[$term->label()];
                $group->addContent($media, 'group_media:' . $media->bundle());

                // tag field_access_term in media
                $media->field_access_terms[] = ['target_id' => $term->id()];
                $media->save();
            }
        }
    }

    /**
     * Remove term(s) in field_access_terms.
     * @param $ne
     * @return void
     */
    public static function clear_term_in_field_access_terms($ne, $group_name) {
        // TODO: search if the node->field_access_terms contain group name
        if (!$ne->hasField('field_access_terms')) {
            return;
        }
        // Get the access terms for the node.
        $terms = $ne->get('field_access_terms')->referencedEntities();
        $i = 0;

        foreach ($terms as $term) {
            if ($term->label() === $group_name) {
                $ne->get("field_access_terms")->removeItem($i);
                $ne->save();
                break;
            }
            $i++;
        }
    }

    /**
     * Clear out existing Group-entity relations
     *
     * @param $entity
     * @return void
     * @throws \Drupal\Core\Entity\EntityStorageException
     */
    public static function clear_group_relation_by_islandora_object($entity) {
        // for each term, loop through groups-entity
        foreach (GroupContent::loadByEntity($entity) as $group_content) {
            $group_content->delete();
        }
        foreach (\Drupal::service('islandora.utils')->getMedia($entity) as $media) {
            self::clear_group_relation_by_media($media);
        }
    }



    /**
     * Tag a media in to Group
     * @param MediaInterface $media
     * @return void
     */
    public static function adding_media_only_into_group(MediaInterface $media) {
        // For standalone media (no parent node)
        if (!$media->hasField('field_access_terms')) {
            return;
        }

        // clear group relation with media
        self::clear_group_relation_by_media($media);

        // get field_access_terms
        $terms = $media->get('field_access_terms')->referencedEntities();
        if (empty($terms)) {
            // no term, exit;
            return;
        }

        // Arrange groups keyed by their name so we can look them up later.
        $groups_by_name = self::arrange_group_by_name();

        foreach ($terms as $term) {
            if (isset($groups_by_name[$term->label()])) {
                $group = $groups_by_name[$term->label()];
                $group->addContent($media, 'group_media:' . $media->bundle());
            }
        }
    }

    /**
     * Print log to apache log.
     */
    public static function print_log($thing) {
        error_log(print_r($thing, TRUE), 0);
    }

    /**
     * Print log to webpage.
     */
    public static function logging($thing) {
        echo "<pre>";
        print_r($thing);
        echo "</pre>";
    }

    /**
     * Clear out existing Group-entity relations
     *
     * @param $entity
     * @return void
     * @throws \Drupal\Core\Entity\EntityStorageException
     */
    public static function clear_group_relation_by_media($media) {
        // clear field_access_terms in media level
        //self::untag_existed_field_access_terms($media);

        // delete group-media relations
        foreach (GroupContent::loadByEntity($media) as $group_content) {
            $group_content->delete();
        }
    }

    /**
     * Redirect to confirm form to add Children nodes to groups
     * @param $form
     * @param $form_state
     * @param $entity
     * @return void
     */
    public static function redirect_adding_childrennode_to_group($form, $form_state, $entity) {
        if ($entity->hasField('field_model') ) {
            // Get associated term model
            $term_id = $entity->get("field_model")->getValue()[0]['target_id'];
            $term_name = Term::load($term_id)->get('name')->value;

            // if collection, redirect to the Confirm form with selecting children to tag
            if ($term_name === "Collection") {
                // check if this node is collection, redirect to confirm form
                $form_state->setRedirect('islandora_group.recursive_apply_accesscontrol', [
                    'nid' => $entity->id(),
                ]);
            }
        }
    }


    /**
     * Return arranged array of Groups with names
     * @return array
     */
    public static function arrange_group_by_name(): array {
        // Arrange groups keyed by their name so we can look them up later.
        $groups = \Drupal::service('entity_type.manager')->getStorage('group')->loadMultiple();
        $groups_by_name = [];
        foreach ($groups as $group) {
            $groups_by_name[$group->label()] = $group;
        }
        return $groups_by_name;
    }

    /**
     * Print log in Recent Log messages.
     */
    public static function drupal_log($msg, $type = "error") {
        switch ($type) {
            case "notice":
                \Drupal::logger(basename(__FILE__, '.module'))->notice($msg);
                break;

            case "log":
                \Drupal::logger(basename(__FILE__, '.module'))->log(RfcLogLevel::NOTICE, $msg);
                break;

            case "warning":
                \Drupal::logger(basename(__FILE__, '.module'))->warning($msg);
                break;

            case "alert":
                \Drupal::logger(basename(__FILE__, '.module'))->alert($msg);
                break;

            case "critical":
                \Drupal::logger(basename(__FILE__, '.module'))->critical($msg);
                break;

            case "debug":
                \Drupal::logger(basename(__FILE__, '.module'))->debug($msg);
                break;

            case "info":
                \Drupal::logger(basename(__FILE__, '.module'))->info($msg);
                break;

            case "emergency":
                \Drupal::logger(basename(__FILE__, '.module'))->emergency($msg);
                break;

            default:
                \Drupal::logger(basename(__FILE__, '.module'))->error($msg);
                break;
        }
    }



}