<?php

    require_once('includes/constants.php');
    require_once('includes/config.php');
    // the icons relative to the page: behind the guild's proxy this site is under /items/, where /images/ isn't
    $icons_url = "images/icons/";
    $images_url = "images/";
    require_once($includes_dir . 'mysql.php');
    require_once('includes/guild.php');
    guild_use_active_items();  // (and no item only the closed zones give)
    guild_use_active_zones();  // (what this server has: no zone past its expansion, none a GM only may enter)
    require_once($includes_dir . 'functions.php');

    /* Handles PJAX requests */
    if(isset($_GET['v_ajax'])){
        require_once('routes.php');
        echo $print_buffer;
        if($page_title && !isset($_GET['v_tooltip'])){
            $footer_javascript .= '
                <script type="text/javascript">
                    $("#title").html("<h1>' . $page_title . '</h1>");
                    document.title = ' . json_encode(guild_site_title($page_title)) . ';
                </script>
            ';
        }
        echo $footer_javascript;
        exit;
    }

    $start = microtime(true);
    $debug_queries = "";
    $print_buffer = "";
?>

<!DOCTYPE html>
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">

    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <title><?php echo htmlspecialchars(guild_site_title($page_title ?? '')); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="<?php echo htmlspecialchars(guild_dashboard_url('/logo.jpg'), ENT_QUOTES); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <script>
        /* the guild dashboard's light or dark, kept the same way (its "theme"), before anything draws */
        try { var t = localStorage.getItem("theme"); if (t) document.documentElement.setAttribute("data-theme", t); } catch (e) {}
    </script>
    <link rel="stylesheet" type="text/css" href="includes/alla.css">
    <link rel="stylesheet" type="text/css" href="./zam_files/global.css">
    <link rel="stylesheet" type="text/css" href="./zam_files/site.css" id="css">
    <link rel="stylesheet" type="text/css" href="includes/js/datatables/media/css/jquery.dataTables.min.css" id="css">

    <script src="includes/js/jquery-3.1.0.min.js"></script>
    <script src="./zam_files/jquery-migrate-1.2.1.min.js"></script>

    <link rel="stylesheet" type="text/css" href="./zam_files/zul.css" id="zul-bar-stylesheet">
    <!-- the guild's look over all of the above: the dashboard's colours, fonts and light or dark -->
    <link rel="stylesheet" type="text/css" href="includes/guild-skin.css">

    <?php
        if($hide_navbar){
            echo '<style>.zul-bar { display:none; } </style>';
        }
    ?>
</head>

<body class="has-zul-bar">
<div id="headjs" style="position: absolute; left: 0px; right: 0px; top: 0px; z-index: 999999999;"></div>
<div class="zul-bar" id="zul-bar" data-mobile="false">
    <div class="zul-bar-inner" id="zul-bar-inner">
    </div>
</div>

<div id="bg-wrapper" style="min-height: auto;">
    <div id="skin-wrap"></div>
    <div id="header">
        <div id="logo" class="guild-logo">
            <div class="guild-home" title="Allakhazam: items, spells, NPCs and zones">
                <a href="?" class="guild-mark" title="The guild's home">
                <img src="<?php echo htmlspecialchars(guild_dashboard_url('/logo.jpg'), ENT_QUOTES); ?>" alt="" width="56" height="56" class="guild-logo-img"></a>
                <span><a href="?" class="guild-title"><b>Allakhazam</b></a><small><a href="?a=items">items</a> · <a href="?a=spells">spells</a> · <a href="?a=npcs">NPCs</a> · <a href="?a=zonelist">zones</a></small></span>
            </div>
            <button type="button" class="guild-menu" id="guild-menu" title="The menu">&#9776; Menu</button>
            <button type="button" class="guild-theme" id="guild-theme" title="Light or dark">&#9680;</button>
        </div>

        <form id="global_search">
            <input id="qq" type="text" onfocus="this.select()" value="<?php echo $_GET['q']; ?>" autocomplete="off" placeholder="Search Items, NPCs, Zones etc.">
            <a href="javascript:document.search.submit();"></a>
        </form>

        <div id="nav">
            <ul id="menu_horiz">
                <li class="has-sub first-child  nc-home"><a href="?">Home</a>
                    <div><em></em><var></var><strong></strong>
                        <ul>
                            <li class="first-child last-child  nc-zam-tools"><a href="http://eqemulator.org">EQEmulator</a></li>
                        </ul>
                    </div>
                </li>
            </ul>
        </div>
    </div>
    <div id="wrapper">
        <div id="body">
            <div id="cols">
                <div id="col-main">
                    <div id="col-main-inner">
                        <div id="col-main-inner-2">
                            <div id="col-main-inner-3">
                                <div id="buffer-top"></div>
                                <div style="width:100%; overflow: hidden;">
                                    <div class="side_menu" style="width:200px; display: inline-block; float: left;">
                                        <table border="0">

                                            <tbody>
                                            <tr>
                                                <td class="menuh" nowrap="1">Main...</td>
                                            </tr>
                                            <tr>
                                                <td nowrap="1" class="menu_item">
                                                    <li><a href="?">Home</a>
                                                    </li>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td  class="menu_item">
                                                    <li><a href="http://www.eqemulator.org">EQEmulator</a>
                                                    </li>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="menuh" nowrap="1">Zones...</td>
                                            </tr>
                                            <tr>
                                                <td  class="menu_item">
                                                    <li><a href="?a=zonelist">Zones by Era</a></li>
                                                    <li><a href="?a=zones">Populated Zones</a> </li>
                                                    <li><a href="?a=zones_by_level">Zones by Level</a> </li>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="menuh" nowrap="1">Guild...</td>
                                            </tr>
                                            <tr>
                                                <td  class="menu_item">
                                                    <li><a href="?a=portal&page=reports">Daily Reports</a> </li>
                                                    <li><a href="?a=portal&page=named">Named</a> </li>
                                                    <li><a href="?a=raid_targets">Raid Targets</a> </li>
                                                    <li><a href="?a=portal&page=incidents">Incidents</a> </li>
                                                    <li><a href="?a=portal&page=backlog">Backlog</a> </li>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="menuh" nowrap="1">Docs...</td>
                                            </tr>
                                            <tr>
                                                <td  class="menu_item">
                                                    <li><a href="?a=portal&page=releases">Releases</a> </li>
                                                    <li><a href="?a=portal&page=docs&doc=item-scores">How items are scored</a> </li>
                                                    <li><a href="?a=portal&page=docs&doc=server-changes">Our server fork</a> </li>
                                                    <li><a href="?a=portal&page=docs&doc=headless-server">Running the headless server</a> </li>
                                                    <li><a href="?a=portal&page=docs&doc=headless-api">Headless bot API</a> </li>
                                                    <li><a href="?a=portal&page=docs&doc=how-we-play">How we play (our AI layer)</a> </li>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="menuh" nowrap="1">Quests...</td>
                                            </tr>
                                            <tr>
                                                <td  class="menu_item">
                                                    <li><a href="?a=quests">All Quests</a> </li>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="menuh" nowrap="1">Items...</td>
                                            </tr>
                                            <tr>
                                                <td  class="menu_item">
                                                    <li><a href="?a=items">Item Search</a> </li>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="menuh" nowrap="1">Spells...</td>
                                            </tr>
                                            <tr>
                                                <td  class="menu_item">
                                                    <li><a href="?a=spells">Spell Search</a></li>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="menuh" nowrap="1">Factions...</td>
                                            </tr>
                                            <tr>
                                                <td class="menu_item" nowrap="1">
                                                    <li><a href="?a=factions">Faction Search</a> </li>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="menuh" nowrap="1">Bestiary...</td>
                                            </tr>
                                            <tr>
                                                <td  class="menu_item">
                                                    <li><a href="?a=npcs">NPC Search</a> </li>
                                                    <li><a href="?a=advanced_npcs">Advanced NPC Search</a> </li>
                                                    <li><a href="?a=pets">Pets</a> </li>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="menuh" nowrap="1">Trade skills...</td>
                                            </tr>
                                            <tr>
                                                <td  class="menu_item">
                                                    <li><a href="?a=recipes&">Recipe
                                                            Search</a>
                                                    </li>
                                                </td>
                                            </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="page-content" style="margin-left: 220px;">
                                        <div id="title"></div>
                                        <div class="page-content-ajax">
                                            <?php

                                                require_once('routes.php');

                                                if($print_buffer){
                                                    print $print_buffer;
                                                }

                                                if($page_title){
                                                    $footer_javascript .= '
                                                        <script type="text/javascript">
                                                            $("#title").html("<h1>' . $page_title . '</h1>");
                                                            document.title = ' . json_encode(guild_site_title($page_title)) . ';
                                                        </script>
                                                    ';

                                                }
                                            ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="clear"></div>
                                <div id="buffer-bottom"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php
        $end = microtime(true);
        $time = number_format(($end - $start), 2);
        $page_load_time = 'This page loaded in ' . $time . ' seconds';
    ?>

    <footer>
        <div class="block-content pad10" style="line-height:24px">
            <ul class="site-footer">
                <?php

                    echo $page_load_time;

                    if($mysql_debugging){
                        print $debug_queries;
                    }

                ?>
            </ul>
            <div class="clear"></div>
        </div>

        <div class="div15"></div>

        <script src="includes/js/footer.js"></script>

        <?php
            if($use_pace_loader){
                echo '
                    <link rel="stylesheet" type="text/css" href="includes/css/pace.css" id="css">
                    <script src="includes/js/pace.min.js"></script>
                ';
            }
        ?>

        <script src="includes/js/pjax.js"></script>
        <script src="includes/js/zam_tooltips.js"></script>
        <script src="includes/js/datatables/media/js/jquery.dataTables.min.js"></script>
        <script type="text/javascript">
            Nav.init()
            // phones: the menu is above the page, closed until asked for (and closed again when something in it is chosen)
            document.getElementById("guild-menu").onclick = function () { document.body.classList.toggle("menu-open"); };
            document.querySelector(".side_menu").addEventListener("click", function (e) {
                if (e.target.closest("a")) document.body.classList.remove("menu-open");
            });
            document.getElementById("guild-theme").onclick = function () {
                var now = document.documentElement.getAttribute("data-theme") === "light" ? "dark" : "light";
                document.documentElement.setAttribute("data-theme", now);
                try { localStorage.setItem("theme", now); } catch (e) {}
            };
        </script>
        <?php
            if($footer_javascript){
                echo $footer_javascript;
            }

        ?>
    </footer>

    <div class="copyright">© 2016 EQEmulator :: Akkadius</div>

</div>


</body>

</html>