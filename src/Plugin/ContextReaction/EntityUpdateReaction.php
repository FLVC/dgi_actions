<?php

namespace Drupal\dgi_actions\Plugin\ContextReaction;

use Drupal\islandora\PresetReaction\PresetReaction;

/**
 * Entity update context reaction.
 *
 * @ContextReaction(
 *   id = "dgi_actions_entity_update_reaction",
 *   label = @Translation("Updates an identifier")
 * )
 */
class EntityUpdateReaction extends PresetReaction {}
