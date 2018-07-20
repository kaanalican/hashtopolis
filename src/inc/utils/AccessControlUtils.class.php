<?php

use DBA\User;
use DBA\QueryFilter;
use DBA\RightGroup;

class AccessControlUtils {
  /**
   * @param int $groupId
   * @param array $perm
   * @throws HTException
   * @return boolean
   */
  public static function updateGroupPermissions($groupId, $perm) {
    global $FACTORIES;
    
    $group = AccessControlUtils::getGroup($groupId);
    if ($group->getPermissions() == 'ALL') {
      throw new HTException("Administrator group cannot be changed!");
    }
    
    $newArr = [];
    foreach ($perm as $p) {
      $split = explode("-", $p);
      if (sizeof($split) != 2 || !in_array($split[1], array("0", "1"))) {
        continue; // ignore invalid submits
      }
      $constants = DAccessControl::getConstants();
      foreach ($constants as $constant) {
        if (is_array($constant)) {
          $constant = $constant[0];
        }
        if ($split[0] == $constant) {
          $newArr[$constant] = ($split[1] == "1") ? true : false;
        }
      }
    }
    $group->setPermissions(json_encode($newArr));
    $FACTORIES::getRightGroupFactory()->update($group);
    
    $acl = new AccessControl(null, $group->getId());
    $arr = $newArr;
    $changes = false;
    foreach ($newArr as $constant => $set) {
      if ($set == true) {
        continue;
      }
      else if ($acl->givenByDependency($constant)) {
        $arr[$constant] = true;
        $changes = true;
      }
    }
    $group->setPermissions(json_encode($arr));
    $FACTORIES::getRightGroupFactory()->update($group);
    
    return $changes;
  }
  
  /**
   * @param string $groupName
   * @throws HTException
   * @return RightGroup
   */
  public static function createGroup($groupName) {
    global $FACTORIES;
    
    if (strlen($groupName) == 0 || strlen($groupName) > DLimits::ACCESS_GROUP_MAX_LENGTH) {
      throw new HTException("Permission group name is too short or too long!");
    }
    
    $qF = new QueryFilter(RightGroup::GROUP_NAME, $groupName, "=");
    $check = $FACTORIES::getRightGroupFactory()->filter(array($FACTORIES::FILTER => $qF), true);
    if ($check !== null) {
      throw new HTException("There is already an permission group with the same name!");
    }
    $group = new RightGroup(0, $groupName, "[]");
    $group = $FACTORIES::getRightGroupFactory()->save($group);
    return $group;
  }
  
  /**
   * @param int $groupId
   * @throws HTException
   */
  public static function deleteGroup($groupId) {
    global $FACTORIES;
    
    $group = AccessControlUtils::getGroup($groupId);
    $qF = new QueryFilter(User::RIGHT_GROUP_ID, $group->getId(), "=");
    $count = $FACTORIES::getUserFactory()->countFilter(array($FACTORIES::FILTER => $qF));
    if ($count > 0) {
      throw new HTException("You cannot delete a group which has still users belonging to it!");
    }
    
    // delete permission group
    $FACTORIES::getRightGroupFactory()->delete($group);
  }
  
  /**
   * @param int $groupId
   * @throws HTException
   * @return RightGroup
   */
  public static function getGroup($groupId) {
    global $FACTORIES;
    
    $group = $FACTORIES::getRightGroupFactory()->get($groupId);
    if ($group === null) {
      throw new HTException("Invalid group!");
    }
    return $group;
  }
}