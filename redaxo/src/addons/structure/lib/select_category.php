<?php

/**
 * @package redaxo\structure
 */
class rex_category_select extends rex_select
{
    /**
     * @var bool
     */
    private $ignore_offlines;
    /**
     * @var null|int
     */
    private $clang;
    /**
     * @var bool
     */
    private $check_perms;
    /**
     * @var bool
     */
    private $add_homepage;

    /**
     * @var int|int[]|null
     */
    private $rootId;

    private $loaded;

    public function __construct($ignoreOfflines = false, $clang = false, $checkPerms = true, $addHomepage = true)
    {
        $this->ignore_offlines = $ignoreOfflines;
        $this->clang = false === $clang ? null : $clang;
        $this->check_perms = $checkPerms;
        $this->add_homepage = $addHomepage;
        $this->rootId = null;
        $this->loaded = false;

        parent::__construct();
    }

    /**
     * Kategorie-Id oder ein Array von Kategorie-Ids als Wurzelelemente der Select-Box.
     *
     * @param int|int[]|null $rootId Kategorie-Id oder Array von Kategorie-Ids zur Identifikation der Wurzelelemente
     */
    public function setRootId($rootId)
    {
        $this->rootId = $rootId;
    }

    protected function addCatOptions()
    {
        if ($this->add_homepage) {
            $this->addOption('Homepage', 0);
        }

        if (null !== $this->rootId) {
            if (is_array($this->rootId)) {
                foreach ($this->rootId as $rootId) {
                    if ($rootCat = rex_category::get($rootId, $this->clang)) {
                        $this->addCatOption($rootCat, 0);
                    }
                }
            } else {
                if ($rootCat = rex_category::get($this->rootId, $this->clang)) {
                    $this->addCatOption($rootCat, 0);
                }
            }
        } else {
            if (!$this->check_perms || rex::getUser()->getComplexPerm('structure')->hasCategoryPerm(0)) {
                if ($rootCats = rex_category::getRootCategories($this->ignore_offlines, $this->clang)) {
                    foreach ($rootCats as $rootCat) {
                        $this->addCatOption($rootCat);
                    }
                }
            } elseif (rex::getUser()->getComplexPerm('structure')->hasMountpoints()) {
                $mountpoints = rex::getUser()->getComplexPerm('structure')->getMountpointCategories();
                foreach ($mountpoints as $cat) {
                    if (!$this->ignore_offlines || $cat->isOnline()) {
                        $this->addCatOption($cat, 0);
                    }
                }
            }
        }
    }

    protected function addCatOption(rex_category $cat, $group = null)
    {
        if (!$this->check_perms ||
                $this->check_perms && rex::getUser()->getComplexPerm('structure')->hasCategoryPerm($cat->getId())
        ) {
            $cid = $cat->getId();
            $cname = $cat->getName() . ' [' . $cid . ']';

            if (null === $group) {
                $group = $cat->getParentId();
            }

            $this->addOption($cname, $cid, $cid, $group);
            $childs = $cat->getChildren($this->ignore_offlines);
            if (is_array($childs)) {
                foreach ($childs as $child) {
                    $this->addCatOption($child);
                }
            }
        }
    }

    public function get()
    {
        if (!$this->loaded) {
            $this->addCatOptions();
            $this->loaded = true;
        }

        return parent::get();
    }

    protected function outGroup($parentId, $level = 0)
    {
        if ($level > 100) {
            // nur mal so zu sicherheit .. man weiss nie ;)
            throw new rex_exception('select->_outGroup overflow');
        }

        $ausgabe = '';
        $group = $this->getGroup($parentId);
        if (!is_array($group)) {
            return '';
        }
        foreach ($group as $option) {
            $name = $option[0];
            $value = $option[1];
            $id = $option[2];
            if (0 == $id || !$this->check_perms || ($this->check_perms && rex::getUser()->getComplexPerm('structure')->hasCategoryPerm($option[2]))) {
                $ausgabe .= $this->outOption($name, $value, $level);
            } elseif (($this->check_perms && rex::getUser()->getComplexPerm('structure')->hasCategoryPerm($option[2]))) {
                --$level;
            }

            $subgroup = $this->getGroup($id, true);
            if (false !== $subgroup) {
                $ausgabe .= $this->outGroup($id, $level + 1);
            }
        }
        return $ausgabe;
    }
}
