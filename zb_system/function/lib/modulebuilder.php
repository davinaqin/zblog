<?php
// 兼容代码
function BuildModule_catalog() {
    return ModuleBuilder::Catalog();
}
function BuildModule_calendar($date = '') {
    return ModuleBuilder::Calendar($date);
}
function BuildModule_comments() {
    return ModuleBuilder::Comments();
}
function BuildModule_previous() {
    return ModuleBuilder::LatestArticles();
}
function BuildModule_archives() {
    return ModuleBuilder::Archives();
}
function BuildModule_navbar() {
    return ModuleBuilder::Navbar();
}
function BuildModule_tags() {
    return ModuleBuilder::TagList();
}
function BuildModule_authors($level = 4) {
    return ModuleBuilder::AuthorList($level);
}
function BuildModule_statistics($array = array()) {
    return ModuleBuilder::Statistics($array);
}

class ModuleBuilder {

    public static $Modules = array();
    public static $readymodules = array(); #模块
    public static $readymodules_function = array(); #模块函数
    public static $readymodules_parameters = array(); #模块函数的参数

    public static function BuildModule() {
        global $zbp;
        foreach (ModuleBuilder::$readymodules as $modfilename) {
            if (isset($zbp->modulesbyfilename[$modfilename])) {
                $m = $zbp->modulesbyfilename[$modfilename];
                $m->Build();
                $m->Save();
            }
        }
    }

    /**
     * 重建模块
     * @param string $modfilename 模块名
     * @param string $userfunc 用户函数
     */
    public static function RegBuildModule($modfilename, $userfunc) {
        if(function_exists($userfunc)){
            ModuleBuilder::$readymodules_function[$modfilename] = $userfunc;
        }
        if (strpos($userfunc, '::') !== false) {
            $a=explode('::', $userfunc);
            if(method_exists($a[0], $a[1])){
                ModuleBuilder::$readymodules_function[$modfilename] = $userfunc;
            }
        }
    }

    /**
     * 添加模块
     * @param string $modfilename 模块名
     * @param null $parameters 模块参数
     */
    public static function AddBuildModule($modfilename, $parameters = null) {
        ModuleBuilder::$readymodules[$modfilename] = $modfilename;
        ModuleBuilder::$readymodules_parameters[$modfilename] = $parameters;
    }

    /**
     * 删除模块
     * @param string $modfilename 模块名
     */
    public static function DelBuildModule($modfilename) {
        unset(ModuleBuilder::$readymodules[$modfilename]);
        unset(ModuleBuilder::$readymodules_function[$modfilename]);
        unset(ModuleBuilder::$readymodules_parameters[$modfilename]);
    }

    /**
     * 导出网站分类模块数据
     * @return string 模块内容
     * @todo 必须重写
     */
    public static function Catalog() {
        global $zbp;

        $template = $zbp->template;
        $tags = array();

        $tags['style'] = $zbp->option['ZC_MODULE_CATALOG_STYLE'];
        $tags['maxLi'] = $zbp->modulesbyfilename['catalog']->MaxLi;
        $tags['catalogs'] = $zbp->categorysbyorder;

        $template->SetTagsAll($tags);
        $ret = $template->Output('module-catalog');

        return $ret;
    }

    /**
     * 导出日历模块数据
     * @param string $date 日期
     * @return string 模块内容
     */
    public static function Calendar($date = '') {
        global $zbp;
        $template = $zbp->template;
        $tags = array();

        if ($date == '') {
            $date = date('Y-m', time());
        }
        $tags['date'] = $date;

        $url = new UrlRule($zbp->option['ZC_DATE_REGEX']);
        $url->Rules['{%day%}'] = 1;

        $value = strtotime('-1 month', strtotime($date));
        $tags['prevMonth'] = date('n', $value);
        $tags['prevYear'] = date('Y', $value);
        $url->Rules['{%date%}'] = $tags['prevYear'] . '-' . $tags['prevMonth'];
        $url->Rules['{%year%}'] = $tags['prevYear'];
        $url->Rules['{%month%}'] = $tags['prevMonth'];
        $tags['prevMonthUrl'] = $url->Make();

        $value = strtotime($date);
        $tags['nowMonth'] = date('n', $value);
        $tags['nowYear'] = date('Y', $value);
        $url->Rules['{%date%}'] = $tags['nowYear'] . '-' . $tags['nowMonth'];
        $url->Rules['{%year%}'] = $tags['nowYear'];
        $url->Rules['{%month%}'] = $tags['nowMonth'];
        $tags['nowMonthUrl'] = $url->Make();

        $value = strtotime('+1 month', strtotime($date));
        $tags['nextMonth'] = date('n', $value);
        $tags['nextYear'] = date('Y', $value);
        $url->Rules['{%date%}'] = $tags['nextYear'] . '-' . $tags['nextMonth'];
        $url->Rules['{%year%}'] = $tags['nextYear'];
        $url->Rules['{%month%}'] = $tags['nextMonth'];
        $tags['nextMonthUrl'] = $url->Make();

        $fdate = strtotime($date);
        $ldate = (strtotime(date('Y-m-t', strtotime($date))) + 60 * 60 * 24);
        $sql = $zbp->db->sql->Select(
            $zbp->table['Post'],
            array('log_ID', 'log_PostTime'),
            array(
                array('=', 'log_Type', '0'),
                array('=', 'log_Status', '0'),
                array('BETWEEN', 'log_PostTime', $fdate, $ldate),
            ),
            array('log_PostTime' => 'ASC'),
            null,
            null
        );
        $array = $zbp->db->Query($sql);
        $arraydate = array();
        foreach ($array as $value) {
            $key = date('j', $value[$zbp->datainfo['Post']['PostTime'][0]]);
            if (!isset($arraydate[$key])) {
                $fullDate = $tags['nowYear'] . '-' . $tags['nowMonth'] . '-' . $key;
                $url->Rules['{%date%}'] = $fullDate;
                $url->Rules['{%year%}'] = $tags['nowYear'];
                $url->Rules['{%month%}'] = $tags['nowMonth'];
                $url->Rules['{%day%}'] = $key;
                $arraydate[$key] = array(
                    'Date' => $fullDate,
                    'Url' => $url->Make(),
                    'Count' => 0
                );
            }
            $arraydate[$key]['Count']++;
        }
        $tags['arraydate'] = $arraydate;
        $tags['module'] = $zbp->modulesbyfilename['calendar'];
        $template->SetTagsAll($tags);
        $ret = $template->Output('module-calendar');

        return $ret;

    }

    /**
     * 导出最新留言模块数据
     * @return string 模块内容
     */
    public static function Comments() {
        global $zbp;
        $template = $zbp->template;
        $tags = array();

        $tags['module'] = $zbp->modulesbyfilename['comments'];
        $i = $zbp->modulesbyfilename['comments']->MaxLi;
        if ($i == 0) {
            $i = 10;
        }
        $tags['maxLi'] = $i;
        $comments = $zbp->GetCommentList('*', array(array('=', 'comm_IsChecking', 0)), array('comm_PostTime' => 'DESC'), $i, null);
        $tags['comments'] = $comments;

        $template->SetTagsAll($tags);
        $ret = $template->Output('module-comments');

        return $ret;
    }

    /**
     * 导出最近发表文章模块数据
     * @return string 模块内容
     */
    public static function LatestArticles() {
        global $zbp;
        $template = $zbp->template;
        $tags = array();

        $tags['module'] = $zbp->modulesbyfilename['comments'];
        $i = $zbp->modulesbyfilename['comments']->MaxLi;
        if ($i == 0) {
            $i = 10;
        }
        $tags['maxLi'] = $i;
        $articles = $zbp->GetArticleList('*', array(array('=', 'log_Type', 0), array('=', 'log_Status', 0)), array('log_PostTime' => 'DESC'), $i, null, false);
        $tags['articles'] = $articles;

        $template->SetTagsAll($tags);
        $ret = $template->Output('module-previous');

        return $ret;
    }

    /**
     * 导出文章归档模块数据
     * @return string 模块内容
     */
    public static function Archives() {
        global $zbp;
        $template = $zbp->template;
        $tags = array();
        $urls = array();//array(url,name,count);

        $maxli = $zbp->modulesbyfilename['archives']->MaxLi;
        if ($maxli < 0) {
            return '';
        }

        $sql = $zbp->db->sql->Select($zbp->table['Post'], array('log_PostTime'), null, array('log_PostTime' => 'DESC'), array(1), null);

        $array = $zbp->db->Query($sql);

        if (count($array) == 0) {
            return '';
        }

        $ldate = array(date('Y', $array[0]['log_PostTime']), date('m', $array[0]['log_PostTime']));

        $sql = $zbp->db->sql->Select($zbp->table['Post'], array('log_PostTime'), null, array('log_PostTime' => 'ASC'), array(1), null);

        $array = $zbp->db->Query($sql);

        if (count($array) == 0) {
            return '';
        }

        $fdate = array(date('Y', $array[0]['log_PostTime']), date('m', $array[0]['log_PostTime']));

        $arraydate = array();

        for ($i = $fdate[0]; $i < $ldate[0] + 1; $i++) {
            for ($j = 1; $j < 13; $j++) {
                $arraydate[] = strtotime($i . '-' . $j);
            }
        }

        foreach ($arraydate as $key => $value) {
            if ($value - strtotime($ldate[0] . '-' . $ldate[1]) > 0) {
                unset($arraydate[$key]);
            }

            if ($value - strtotime($fdate[0] . '-' . $fdate[1]) < 0) {
                unset($arraydate[$key]);
            }

        }

        $arraydate = array_reverse($arraydate);
        $s = '';
        $i = 0;

        foreach ($arraydate as $key => $value) {
            if ($i >= $maxli && $maxli > 0) {
                break;
            }

            $url = new UrlRule($zbp->option['ZC_DATE_REGEX']);
            $url->Rules['{%date%}'] = date('Y-n', $value);
            $url->Rules['{%year%}'] = date('Y', $value);
            $url->Rules['{%month%}'] = date('n', $value);
            $url->Rules['{%day%}'] = 1;

            $fdate = $value;
            $ldate = (strtotime(date('Y-m-t', $value)) + 60 * 60 * 24);
            $sql = $zbp->db->sql->Count($zbp->table['Post'], array(array('COUNT', '*', 'num')), array(array('=', 'log_Type', '0'), array('=', 'log_Status', '0'), array('BETWEEN', 'log_PostTime', $fdate, $ldate)));
            $n = GetValueInArrayByCurrent($zbp->db->Query($sql), 'num');
            if ($n > 0) {
                $urls[]=array($url->Make(),str_replace(array('%y%', '%m%'), array(date('Y', $fdate), date('n', $fdate)), $zbp->lang['msg']['year_month']),$n);
                $i++;
            }
        }

        $tags['urls'] = $urls;

        $template->SetTagsAll($tags);
        $ret = $template->Output('module-archives');

        return $ret;
    }

    /**
     * 导出导航模块数据
     * @return string 模块内容
     */
    public static function Navbar() {
        global $zbp;
        $template = $zbp->template;
        $tags = array();

        $s = $zbp->modulesbyfilename['navbar']->Content;

        $a = array();
        preg_match_all('/<li id="navbar-(page|category|tag)-(\d+)">/', $s, $a);

        $b = $a[1];
        $c = $a[2];
        foreach ($b as $key => $value) {

            if ($b[$key] == 'page') {

                $type = 'page';
                $id = $c[$key];
                $o = $zbp->GetPostByID($id);
                $url = $o->Url;
                $name = $o->Title;

                $a = '<li id="navbar-' . $type . '-' . $id . '"><a href="' . $url . '">' . $name . '</a></li>';
                $s = preg_replace('/<li id="navbar-' . $type . '-' . $id . '">.*?<\/a><\/li>/', $a, $s);

            }
            if ($b[$key] == 'category') {

                $type = 'category';
                $id = $c[$key];
                $o = $zbp->GetCategoryByID($id);
                $url = $o->Url;
                $name = $o->Name;

                $a = '<li id="navbar-' . $type . '-' . $id . '"><a href="' . $url . '">' . $name . '</a></li>';
                $s = preg_replace('/<li id="navbar-' . $type . '-' . $id . '">.*?<\/a><\/li>/', $a, $s);

            }
            if ($b[$key] == 'tag') {

                $type = 'tag';
                $id = $c[$key];
                $o = $zbp->GetTagByID($id);
                $url = $o->Url;
                $name = $o->Name;

                $a = '<li id="navbar-' . $type . '-' . $id . '"><a href="' . $url . '">' . $name . '</a></li>';
                $s = preg_replace('/<li id="navbar-' . $type . '-' . $id . '">.*?<\/a><\/li>/', $a, $s);

            }
        }

        $tags['content'] = $s;

        $template->SetTagsAll($tags);
        $ret = $template->Output('module-navbar');

        return $ret;
    }

    /**
     * 导出tags模块数据
     * @return string 模块内容
     */
    public static function TagList() {
        global $zbp;
        $template = $zbp->template;
        $tags = array();
        $urls = array();//array(url,name,count);

        $i = $zbp->modulesbyfilename['tags']->MaxLi;
        if ($i == 0) {
            $i = 25;
        }

        $array = $zbp->GetTagList('*', '', array('tag_Count' => 'DESC'), $i, null);
        $array2 = array();
        foreach ($array as $tag) {
            $array2[$tag->ID] = $tag;
        }
        ksort($array2);

        foreach ($array2 as $tag) {
            $urls[]=array($tag->Url,$tag->Name,$tag->Count);
        }

        $tags['urls'] = $urls;

        $template->SetTagsAll($tags);
        $ret = $template->Output('module-tags');

        return $ret;
    }

    /**
     * 导出用户列表模块数据
     * @param int $level 要导出的用户最低等级，默认为4（即协作者）
     * @return string 模块内容
     */
    public static function AuthorList($level = 4) {
        global $zbp;
        $template = $zbp->template;
        $tags = array();
        $authors = array();

        $w = array();
        $w[] = array('<=', 'mem_Level', $level);

        $array = $zbp->GetMemberList('*', $w, array('mem_ID' => 'ASC'), null, null);

        foreach ($array as $member) {
            unset($member->Guid);
            unset($member->Password);
            $authors[]=$member;
        }

        $tags['authors'] = $authors;
        $template->SetTagsAll($tags);
        $ret = $template->Output('module-authors');

        return $ret;
    }

    /**
     * 导出网站统计模块数据
     * @param array $array
     * @return string 模块内容
     */
    public static function Statistics($array = array()) {
        global $zbp;
        $template = $zbp->template;
        $tags = array();
        $allinfo = array();

        $all_artiles = 0;
        $all_pages = 0;
        $all_categorys = 0;
        $all_tags = 0;
        $all_views = 0;
        $all_comments = 0;

        if (count($array) == 0) {
            return $zbp->modulesbyfilename['statistics']->Content;
        }

        if (isset($array[0])) {
            $all_artiles = $array[0];
        }

        if (isset($array[1])) {
            $all_pages = $array[1];
        }

        if (isset($array[2])) {
            $all_categorys = $array[2];
        }

        if (isset($array[3])) {
            $all_tags = $array[3];
        }

        if (isset($array[4])) {
            $all_views = $array[4];
        }

        if (isset($array[5])) {
            $all_comments = $array[5];
        }

        $allinfo[]=array($zbp->lang['msg']['all_artiles'],$all_artiles);
        $allinfo[]=array($zbp->lang['msg']['all_pages'],$all_pages);
        $allinfo[]=array($zbp->lang['msg']['all_categorys'],$all_categorys);
        $allinfo[]=array($zbp->lang['msg']['all_tags'],$all_tags);
        $allinfo[]=array($zbp->lang['msg']['all_comments'],$all_comments);
        if (!$zbp->option['ZC_VIEWNUMS_TURNOFF'] || $zbp->option['ZC_LARGE_DATA']) {
            $allinfo[]=array($zbp->lang['msg']['all_views'],$all_views);
        }

        $zbp->modulesbyfilename['statistics']->Type = "ul";


        $tags['allinfo'] = $allinfo;
        $template->SetTagsAll($tags);
        $ret = $template->Output('module-statistics');

        return $ret;
    }

}
