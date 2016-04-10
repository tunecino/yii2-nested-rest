# yii2-nested-rest

Adds nested resources routing support along with related actions and relationship handlers to the [Yii RESTful API framework](http://www.yiiframework.com/doc-2.0/guide-rest-quick-start.html).

**Note: This is an alpha release. while not yet covered by tests and version number is less than 1.0.0 extra precautions should be taken before using in production.**

## How It Works
This extension doesn't replace any of the built-in REST components. It is about a custom `UrlRule` class designed to be used along with the default one:

```php
'rules' => [

    // Yii defaults UrlRule class for single classic endpoints
    [
        'class' => 'yii\rest\UrlRule',
        'controller' => ['team','player','skill'],
    ],
    
    // the custom UrlRule class to generate nested rules based in relations
    [
        'class' => 'tunecino\nestedrest\UrlRule',
        'modelClass' => 'app\models\Team',
        'relations' => ['players'],
    ],
    [
        'class' => 'tunecino\nestedrest\UrlRule',
        'modelClass' => 'app\models\Player',
        'relations' => ['team','skills'],
    ],
]
```

And a collection of extra `actions` to be implemented when needed inside your REST Controllers. To see how it works within an example, if within the previous configurations we expect `team` and `player` to share a *one-to-many* relationship while  `player` and `skill` shares a *many-to-many* relation within a junction table and having an extra column called `level` in that junction table to set the player's level of mastery of the linked skill, then this extension may help achieving the following HTTP requests:
     
```php
// get the players 2, 3 and 4 from team 1
GET /teams/1/players/2,3,4

// list all skills of player 5
GET /players/5/skills

// put the players 5 and 6 in team 1
PUT /teams/1/players/5,6

// create a new player and put him in team 1
POST /teams/1/players
{name: 'Didier Drogba', position: 'FC'}

// create a new skill called 'dribble' and assign it to player 9 with a related level of 10
POST /players/9/skills
{name: 'dribble', level: 10}

// remove skill 3 from player 2
DELETE /players/2/skills/3

// get all players out of team 2
DELETE /teams/2/players
```

## Installation

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```bash
$ composer require tunecino/yii2-nested-rest
```

or add

```
"tunecino/yii2-nested-rest": "*"
```

to the `require` section of your `composer.json` file.



## Configuration

All the properties used by the custom UrlRule class of this extension will be used to generate multiple instances of the built-in [yii\rest\UrlRule](http://www.yiiframework.com/doc-2.0/yii-rest-urlrule.html) so basically both classes are sharing similar configurations. those are all the possible settings of this extension:
```php
'rules' => [
    [
        'class' => 'tunecino\nestedrest\UrlRule', /* required */
        'modelClass' => 'app\models\Player', /* required */

         /**
         * relations names to be nested with this model
         * relation name should be the same defined in model
         */
        'relations' => ['team','skills'], /* required */

        /**
         * used to generating the 'prefix'.
         * default: the model name pluralized
         */
        'resourceName' => 'players',

        /**
         * also used with 'prefix'. is the expected foreign key.
         * default: $model_name . '_id'
         */
        'linkAttribute' => 'player_id',

        /**
         *  building related rules using 'controller => ['teams' => 'v1/team']' 
         *  instead of 'controller => ['team']'
         */
        'modulePrefix' => 'v1',

        /**
         *  the default list of patterns. they may be all overridden here
         *  or just edited within $only, $except and $extraPatterns properties
         */
        'patterns' => [
            'GET,HEAD {id}' => 'nested-view',
            'GET,HEAD' => 'nested-index',
            'POST' => 'nested-create',
            'PUT {id}' => 'nested-link',
            'DELETE {id}' => 'nested-unlink',
            'DELETE' => 'nested-unlink-all',
            '{id}' => 'options',
            '' => 'options',
        ],

        /**
         *  list of acceptable actions.
         */
        'only' => [],

        /**
         *  actions that should be excluded.
         */
        'except' => [],

        /**
         *  supporting extra actions in addition to those listed in $patterns.
         */
        'extraPatterns' => []
    ],
]
```
As you may notice, `$patterns` is linking 6 new actions different from the basic CRUD actions introduced by the [ActiveController](http://www.yiiframework.com/doc-2.0/yii-rest-activecontroller.html). Those are included in this extension and you will need to manually declare them inside your controller whenever needed. The following is an example of a full implementation within the [controller::actions()](http://www.yiiframework.com/doc-2.0/yii-rest-activecontroller.html#actions%28%29-detail) function:

```php
public function actions() 
{
    $actions = parent::actions(); 
       
    $actions['nested-index'] = [
        'class' => 'tunecino\nestedrest\IndexAction', /* required */
        'modelClass' => $this->modelClass, /* required */
        'checkAccess' => [$this, 'checkAccess'],
    ];

    $actions['nested-view'] = [
        'class' => 'tunecino\nestedrest\ViewAction', /* required */
        'modelClass' => $this->modelClass, /* required */
        'checkAccess' => [$this, 'checkAccess'],
    ];

    $actions['nested-create'] = [
        'class' => 'tunecino\nestedrest\CreateAction', /* required */
        'modelClass' => $this->modelClass, /* required */
        'checkAccess' => [$this, 'checkAccess'],

        /**
         * the scenario to be assigned to the new model before it is validated and saved.
         */
        'scenario' => 'default', /* optional */

        /**
         * the scenario to be assigned to the model class responsible 
         * of handling the data stored in the juction table.
         */
        'viaScenario' => 'default', /* optional */

        /**
         * expect junction table related data to be wrapped in a sub object key in the body request.
         * In the example we gave above we would need to do :
         * POST {name: 'dribble', related: {level: 10}}
         * instead of {name: 'dribble', level: 10}
         */
        'viaWrapper' => 'related' /* optional */
    ];

    $actions['nested-link'] = [
        'class' => 'tunecino\nestedrest\LinkAction', /* required */
        'modelClass' => $this->modelClass, /* required */
        'checkAccess' => [$this, 'checkAccess'],
        
        /**
         * the scenario to be assigned to the model class responsible 
         * of handling the data stored in the juction table.
         */
        'viaScenario' => 'default', /* optional */
    ];

    $actions['nested-unlink'] = [
        'class' => 'tunecino\nestedrest\UnlinkAction', /* required */
        'modelClass' => $this->modelClass, /* required */
        'checkAccess' => [$this, 'checkAccess'],
    ];

    $actions['nested-unlink-all'] = [
        'class' => 'tunecino\nestedrest\UnlinkAllAction', /* required */
        'modelClass' => $this->modelClass, /* required */
        'checkAccess' => [$this, 'checkAccess'],
    ];
    
    return $actions;
}
```

## What you need to know

###1.
This doesn't support models with composite keys. One of the main ideas of building this extension is to provide alternatives to not have to build resources for composite keys models like junction table related models. check the example provided in section **6.** for more details.

###2.
When defining relation names in the config file they should match the method names implemented inside your model *(see [Declaring Relations](http://www.yiiframework.com/doc-2.0/guide-db-active-record.html#declaring-relations) section for more details)*. This extension will do the check and throw an error if they don't match but for performance reasons *(check [this](http://www.yiiframework.com/doc-2.0/guide-db-active-record.html#declaring-relations))* it won't do that DB schema parsing anymore when the application is in *production* mode. in other words verification is made only when`YII_DEBUG` is true.

###3.
When it comes to linking *many-to-many* relations with extra columns in a junction table it is highly recommended to use [via()](http://www.yiiframework.com/doc-2.0/yii-db-activerelationtrait.html#via%28%29-detail) instead of [viaTable()](http://www.yiiframework.com/doc-2.0/yii-db-activequery.html#viaTable%28%29-detail) so the intermediate class can be used by this extension to validate related attributes instead of using [link()](http://www.yiiframework.com/doc-2.0/yii-db-baseactiverecord.html#link%28%29-detail) and saving data without performing validation. please refer to the [Relations via a Junction Table](http://www.yiiframework.com/doc-2.0/guide-db-active-record.html#junction-table) section for more details.

###4.
When unlinking data, if the relation type is between both models is many_to_many, related row in the junction table will be deleted. Otherwise related foreign key will be simply set to NULL in database.

###5.
When linking or unlinking data the model(s) is/are not returned. a `204` response should be expected instead if any change has been made while a `304` response should tell that no change has been made like when asking to link two already linked models.

###6.
When performing any HTTP request, for example `GET /players/9/skills/2`, The custom `UrlRule` will redirect it by default to the route `skill/nested-view` with those 4 extra attributes added to `Yii::$app->request->queryParams`:

```php
relativeClass = 'app/models/player'; // the class name of the relative model
relationName  = 'skills'; // the one you did set in rules configuration.
linkAttribute = 'player_id'; // the foreign key attribute name.
player_id     = 9; // the foreign key attribute and its value
```
Those may be useful when building your own actions or doing extra things like for example, if we add the following inside `app/models/skill` :

```php
protected function getPlayersSharedData()
{
    $params = Yii::$app->request->queryParams;
    $player_id = empty($params['player_id']) ? null : $params['player_id'];

    return ($player_id) ? $this->getSkillsHasPlayers()
                             ->where(['player_id' => $player_id ])
                             ->select('level')
                             ->one() : null;
}

public function fields()
{
    $fields = parent::fields();

    if (!empty(Yii::$app->request->queryParams['player_id'])) {
        $fields['_shared'] = 'playersSharedData';
    }

    return $fields;
}
```

a request like `GET /players/9/skills` or `GET /players/9/skills/2` will also output the related data between both models that is stored in the related junction table:

```php
GET /players/9/skills/2
outputs:
{
  "id": 2,
  "name": "dribble",
  "_shared": {
    "level": 10
  }
}
```