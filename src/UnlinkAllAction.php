<?php
/**
 * @link https://github.com/tunecino/yii2-nested-rest
 * @copyright Copyright (c) 2016 Salem Ouerdani
 * @license http://opensource.org/licenses/BSD-3-Clause
 */

namespace tunecino\nestedrest;

use Yii;

/**
 * UnlinkAllAction implements the API endpoint for unlinking all the models linked to this resource.
 *
 * @author Salem Ouerdani <tunecino@gmail.com>
 */
class UnlinkAllAction extends Action
{
    /**
     * Unlinks all the related models.
     * If the relation type is a many_to_many. related row in the junction table will be deleted.
     * Otherwise related foreign key will be simply set to NULL.
     * A '204' response should be set to headers if any change has been made.
     * @throws InvalidCallException if the models cannot be unlinked
     */
    public function run()
    {
        $relModel = $this->getRelativeModel();

        $relType = $relModel->getRelation($this->relationName);
        $delete = ($relType->multiple === true && $relType->via !== null);

        $relModel->unlinkAll($this->relationName, $delete);

        Yii::$app->getResponse()->setStatusCode(204);
    }
}
