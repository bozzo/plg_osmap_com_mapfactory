<?php
/****************************************************************************************\
**   MapFactory Plugin for osmap                                                       **
**   Copyright (C) 2017 - 2017                                                         **
**   Released under GNU GPL Public License v3                                          **
\****************************************************************************************/

defined('_JEXEC') or die('Direct Access to this location is not allowed.');

/**
 * Handles standard MapFactory trips and categories
 *
 * @package MapFactory
 * @since   1.0
 */
class osmap_com_mapfactory
{
  /**
   * This function is called before a menu item is printed. We use it to set the
   * proper unique ID for the item
   *
   * @param   object  $node   Menu item to be "prepared"
   * @param   array   $params The extension parameters
   * @return  void
   * @since   1.0
   */
  public static function prepareMenuItem(&$node,&$params)
  {
    $link_query = parse_url($node->link);
    parse_str(html_entity_decode($link_query['query']), $link_vars);
    $view = JArrayHelper::getValue($link_vars, 'view', '', '');

    if($view =='trip' /*|| $view =='map'*/)
    {
      $id = intval(JArrayHelper::getValue($link_vars, 'id', 0));
      $node->uid = 'com_mapfactoryt'.$id;
      $node->expandible = false;
    }
    else
    {
      if($view =='category' || $view =='categories')
      {
        $cid = intval(JArrayHelper::getValue($link_vars, 'catid', 0));
        $node->uid = 'com_mapfactoryc'.$cid;
        $node->expandible = true;
      }
    }
  }

  /**
   * Gets the tree of category structure
   *
   * @param   object  $osmap   The OSMap displayer object
   * @param   object  $parent The parent node
   * @param   array   $params The extension parameters
   * @return  void
   * @since   1.0
   */
  public static function getTree($osmap, $parent, &$params)
  {
    if($osmap->isNews) 
    {
      // This component does not provide news content.
      // Don't waste time and resources.
      return;
    }

    $link_query = parse_url($parent->link);
    if(!isset($link_query['query']))
    {
      return;
    }

    parse_str(html_entity_decode($link_query['query']), $link_vars);	
    $view = JArrayHelper::getValue($link_vars, 'view', '', '');

    if($view =='trip' || $view =='map')
    {
	return;
    }

    // Get the parameters
    // Set expand_categories param to determine the search of image items
    $expand_categories = JArrayHelper::getValue($params, 'expand_categories', 1);
    $expand_categories = ((     $expand_categories == 1
                            ||  ($expand_categories == 2 && $osmap->view == 'xml')
                            ||    ($expand_categories == 3 && $osmap->view == 'html')
                            || $osmap->view == 'navigator'
                          ));
    $params['expand_categories'] = $expand_categories;

    $priority = JArrayHelper::getValue($params, 'cat_priority', $parent->priority);
    $changefreq = JArrayHelper::getValue($params, 'cat_changefreq', $parent->changefreq);
    if($priority == '-1')
    {
      $priority = $parent->priority;
    }
    if($changefreq == '-1')
    {
      $changefreq = $parent->changefreq;
    }

    $params['cat_priority'] = $priority;
    $params['cat_changefreq'] = $changefreq;

    $priority = JArrayHelper::getValue($params, 'trip_priority', $parent->priority);
    $changefreq = JArrayHelper::getValue($params, 'trip_changefreq', $parent->changefreq);
    if($priority == '-1')
    {
      $priority = $parent->priority;
    }
    if($changefreq == '-1')
    {
      $changefreq = $parent->changefreq;
    }

    $params['trip_priority'] = $priority;
    $params['trip_changefreq'] = $changefreq;

    $params['max_trips'] = intval(JArrayHelper::getValue($params, 'max_trips', 0));

    $cid = intval(JArrayHelper::getValue($link_vars, 'catid', 1));

    self::expandCategory($osmap, $parent, $cid, $params, $parent->id);
  }

  /**
   * Add category items and images within a category
   *
   * @param   object  $osmap   The osmap displayer object
   * @param   object  $parent The parent node
   * @param   int     $catid  The ID of the category to expand
   * @param   array   $params The extension parameters
   * @param   int     $itemid The itemid to use for this category's children
   * @return  void
   * @since   1.0
   */
  public static function expandCategory($osmap, $parent, $catid, &$params, $itemid)
  {
    // If catid = 1 call getRootCats() to get the cats at most upper level
    if($catid == 1)
    {
      $subcats = self::getAllSubCategories($catid);
    }
    else
    {
      if($params['expand_categories'])
      {
        self::getTrips($osmap, $parent, $catid, $params, $itemid);
      }

      // Get sub-categories of category
      // Returns an array with catids, so construct an array with objects needed for the nodes
      $subcats = self::getAllSubCategories($catid);
    }

    if(count($subcats) > 0)
    {
      $osmap->changeLevel(1);
      foreach($subcats as $subcat)
      {
        $node             = new stdClass();
        $node->id         = $parent->id;
        $node->uid        = $parent->uid.'c'.$subcat->id;
        $node->browserNav = $parent->browserNav;
        $node->priority   = $params['cat_priority'];
        $node->changefreq = $params['cat_changefreq'];

	if ( is_null($subcat->modified_time) || $subcat->modified_time == "0000-00-00 00:00:00") {
		if ( is_null($subcat->created_time) || $subcat->created_time == "0000-00-00 00:00:00") {
			$node->modified   = strtotime("2015-01-01 10:00:00");
		} else {
			$node->modified   = strtotime($subcat->created_time);
		}
	} else {
		$node->modified   = strtotime($subcat->modified_time);
	}
        $node->name       = $subcat->title;
        $node->expandible = true;
        $node->secure     = $parent->secure;
        $node->keywords   = $subcat->title;
        $node->newsItem   = 0;
        $node->slug       = $subcat->id;
        $node->link       = 'index.php?option=com_mapfactory&amp;view=category&amp;catid='.$subcat->id.'&Itemid='.$parent->id;
        $node->itemid     = $parent->id;

        // Print the category node and look recursively for sub-categories
        if($osmap->printNode($node))
        {
          self::expandCategory($osmap, $parent, $subcat->id, $params, $node->itemid);
        }
      }

      $osmap->changeLevel(-1);
    }
  }

  /**
   * Add all trips within a category
   *
   * @param   object  $osmap   The osmap displayer object
   * @param   object  $parent The parent node
   * @param   int     $catid  The ID of the category to expand
   * @param   array   $params The extension parameters
   * @param   int     $itemid The itemid to use for this category's children
   * @return  void
   * @since   1.0
   */
  public static function getTrips($osmap, $parent, $catid, &$params, $Itemid)
  {
    $user = JFactory::getUser();
    $db   = JFactory::getDbo();

    $query = $db->getQuery(true)
          ->select($db->quoteName(array('c.id','c.name','c.modified','c.created')))
          ->from('#__mapfactory_trips AS c')
          ->where('c.published = 1')
          ->where('c.catid = '.$catid)
          ->where('c.access IN ('.implode(',', $user->getAuthorisedViewLevels()).')')
          ->order('c.name');
    $db->setQuery($query);

    $trips = $db->loadObjectList();

    if(count($trips) > 0)
    {
      $osmap->changeLevel(1);
      foreach($trips as $trip)
      {
        $node             = new stdClass();
        $node->id         = $parent->id;
        $node->uid        = $parent->uid . 't' . $trip->id;
        $node->browserNav = $parent->browserNav;
        $node->priority   = $params['trip_priority'];
        $node->changefreq = $params['trip_changefreq'];
        $node->name       = $trip->name;

	if ( $trip->modified == "0000-00-00 00:00:00") {
		if ( $trip->created == "0000-00-00 00:00:00") {
			$node->modified   = strtotime("2015-01-01 12:00:00");
		} else {
			$node->modified   = strtotime($trip->created);
		}
	}else {
		$node->modified   = strtotime($trip->modified);
	}
        $node->expandible = false;
        $node->secure     = $parent->secure;
        $node->keywords   = $trip->name;
        $node->newsItem   = 0;
        $node->language   = null;
        $node->link       = 'index.php?option=com_mapfactory&amp;view=trip&amp;id='.$trip->id.'&Itemid='.$parent->id;
        $osmap->printNode($node);
      }

      $osmap->changeLevel(-1);
    }
  }

  /**
   * Loads the interface object of MapFactory
   *
   * @return  void
   * @since   2.0
   */
  private static function getAllSubCategories($catid)
  {
    $user = JFactory::getUser();
    $db   = JFactory::getDbo();

    $query = $db->getQuery(true)
          ->select($db->quoteName(array('c.id','c.title','c.modified_time','c.created_time')))
          ->from('#__categories AS c')
          ->where('c.published = 1')
          ->where('c.parent_id = '.$catid)
          ->where("c.extension='com_mapfactory'")
          ->where('c.access IN ('.implode(',', $user->getAuthorisedViewLevels()).')')
          ->order('c.lft');
    $db->setQuery($query);

    return $db->loadObjectList();
  }
}
