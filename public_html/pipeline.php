<?php

require_once('../includes/functions.php');
usort($pipeline->releases, 'rsort_releases');

$title = 'nf-core/<br class="d-sm-none">'.$pipeline->name;
$subtitle = $pipeline->description;
$schema_content = '';

require_once('../includes/pipeline_page/components.php');

########
## Figure out what page we're rendering
########

# URL path to readme - redirect to pipeline root
if(endswith($_GET['path'], '/README')){
  header('Location: /'.substr($_GET['path'], 0, -7));
  exit;
}

# Set defaults (Readme tab)
$pagetab = ''; # empty string is home / readme
$release = 'dev';
$latest_release = 'dev';
if(count($pipeline->releases) > 0){
  $release = $latest_release = $pipeline->releases[0]->tag_name;
  $release_url = $pipeline->releases[0]->html_url;
}

# Find release from URL if set
if(count($path_parts) > 1){
  foreach($pipeline->releases as $r){
    if($path_parts[1] == $r->tag_name){
      $release = $r->tag_name;
      $release_url = $releases->html_url;
    }
  }
  if($path_parts[1] == 'dev'){
    $release = 'dev';
    $release_url = null;
  }
}

# Usage docs
if(endswith($_GET['path'], '/usage')){
  $pagetab = 'usage';
  require_once('../includes/pipeline_page/usage.php');
}
# Output docs
else if(endswith($_GET['path'], '/output')){
  $pagetab = 'output';
  require_once('../includes/pipeline_page/output.php');
}
# Stats
else if(endswith($_GET['path'], '/stats')){
  $pagetab = 'stats';
  require_once('../includes/pipeline_page/stats.php');
}
# Releases
else if(endswith($_GET['path'], '/releases')){
  $pagetab = 'releases';
  require_once('../includes/pipeline_page/releases.php');
}
# Some other URL pattern that we don't recognise - 404
else if($_GET['path'] != $pipeline->name && $_GET['path'] != $pipeline->name.'/'.$release){
  header('HTTP/1.1 404 Not Found');
  $suggestion_404_url = '/'.$pipeline->name;
  include('404.php');
  die();
}

# Still the homepage,
if($pagetab == ''){
  require_once('../includes/pipeline_page/docs.php');
}

# Main page nav and header
$url_base = '/'.$pipeline->name.'/'.$release;
$no_print_content = true;
$mainpage_container = false;
include('../includes/header.php');

# Pipeline subheader

# Try to fetch the nextflow_schema.json file for the latest release, to see whether we can have a 'Launch' button
$gh_launch_schema_fn = dirname(dirname(__FILE__))."/api_cache/json_schema/{$pipeline->name}/{$release}.json";
$gh_launch_no_schema_fn = dirname(dirname(__FILE__))."/api_cache/json_schema/{$pipeline->name}/{$release}.NO_SCHEMA";
# Build directories if needed
if (!is_dir(dirname($gh_launch_schema_fn))) {
  mkdir(dirname($gh_launch_schema_fn), 0777, true);
}
// Load cache if not 'dev'
if((!file_exists($gh_launch_schema_fn) && !file_exists($gh_launch_no_schema_fn)) || $release == 'dev'){
  $api_opts = stream_context_create([ 'http' => [ 'method' => 'GET', 'header' => [ 'User-Agent: PHP' ] ] ]);
  $gh_launch_schema_url = "https://api.github.com/repos/nf-core/{$pipeline->name}/contents/nextflow_schema.json?ref={$release}";
  $gh_launch_schema_json = file_get_contents($gh_launch_schema_url, false, $api_opts);
  if(!in_array("HTTP/1.1 200 OK", $http_response_header)){
    # Remember for next time
    file_put_contents($gh_launch_no_schema_fn, '');
  } else {
    # Save cache
    file_put_contents($gh_launch_schema_fn, $gh_launch_schema_json);
  }
}

########
# Get details for the Call To Action button
########
# Text, URL and icon for the CTA button
$cta_txt = $release == "dev" ? 'See the latest code' : 'See version '.$release;
$cta_url = $pipeline->html_url;
$cta_icon = $release == "dev" ? '<i class="fad fa-construction mr-1"></i> ' : '<i class="fas fa-tags mr-1"></i> ';
if(file_exists($gh_launch_schema_fn)){
  $cta_txt = $release == "dev" ? 'Launch development version' : 'Launch version '.$release;
  $cta_url = '/launch?pipeline='.$pipeline->name.'&release='.$release;
  $cta_icon = '<i class="fad fa-rocket-launch mr-1"></i> ';
}
# Build button
$cta_btn = '<a href="'.$cta_url.'" class="btn btn-success btn-lg">'.$cta_icon.$cta_txt.'</a>';

########
# Warning alert box
########
$pipeline_warning = '';
$latest_stable = 'The latest stable release is <a href="/'.$pipeline->name.'/'.$latest_release.'"><code>v'.$latest_release.'</code></a>';
if(count($pipeline->releases) == 0){
  $pipeline_warning = '<div class="alert alert-danger">This pipeline is currently in development and does not yet have any stable releases.</div>';
} else if($release == 'dev'){
  $pipeline_warning = '<div class="alert alert-warning">You are viewing the development version pages for this pipeline. '.$latest_stable.'</div>';
} else if($release != $latest_release){
  $pipeline_warning = '<div class="alert alert-warning">These pages are for an old version of the pipeline (<code>v'.$release.'</code>). '.$latest_stable.'</div>';
}
if($pipeline->archived){
  $pipeline_warning = '<div class="alert alert-warning">This pipeline has been archived and is no longer being actively maintained.</div>';
}

########
# Extra HTML for the header - tags and GitHub URL
########
?>

<div class="mainpage-subheader-heading">
  <div class="container text-center">
    <?php echo $pipeline_warning; ?>
    <p><?php echo $cta_btn; ?></p>
    <p class="mb-0"><a href="<?php echo $pipeline->html_url; ?>" class="text-dark"><i class="fab fa-github"></i> <?php echo $pipeline->html_url; ?></a></p>
  </div>
</div>
<div class="triangle subheader-triangle-down"></div>

<div class="container main-content">

<ul class="nav nav-fill nfcore-subnav">
  <li class="nav-item">
    <a class="nav-link<?php if($pagetab==''){ echo ' active'; } ?>" href="<?php echo $url_base; ?>">Readme</a>
  </li>
  <li class="nav-item">
    <a class="nav-link<?php if($pagetab=='usage'){ echo ' active'; } ?>" href="<?php echo $url_base; ?>/usage">Usage</a>
  </li>
  <li class="nav-item">
    <a class="nav-link<?php if($pagetab=='output'){ echo ' active'; } ?>" href="<?php echo $url_base; ?>/output">Outputs</a>
  </li>
  <li class="nav-item">
    <a class="nav-link<?php if($pagetab=='stats'){ echo ' active'; } ?>" href="/<?php echo $pipeline->name; ?>/stats">Stat<span class="d-none d-sm-inline">istic</span>s</a>
  </li>
  <li class="nav-item">
    <a class="nav-link<?php if($pagetab=='releases'){ echo ' active'; } ?>" href="/<?php echo $pipeline->name; ?>/releases">Releases</a>
  </li>
  <?php if($pagetab == '' || $pagetab == 'output' || $pagetab == 'usage'): ?>
  <li class="pt-1 pl-3">
    <div class="input-group input-group-sm">
      <div class="input-group-prepend">
        <label class="input-group-text" for="version_select"><i class="fas fa-tags"></i></label>
      </div>
      <select class="custom-select custom-select-sm" id="version_select" data-pipeline="<?php echo $pipeline->name?>">
        <?php
        $releases = [];
        foreach($pipeline->releases as $r){
          array_push($releases, $r->tag_name);
        }
        array_push($releases, "dev");
        foreach($releases as $r){
          $selected = $r == $release ? 'selected="selected"' : '';
          echo '<option value="/'.$pipeline->name.'/'.$r.'/'.$pagetab.'" '.$selected.'>'.$r.'</option>';
        }
        ?>
      </select>
    </div>
  </li>
  <?php endif; ?>
</ul>

<?php
########
# Make a row with a column for content for everything except the stats page
########
if($pagetab !== 'stats'){
  echo '<div class="row"><div class="col-lg-8 order-lg-1">';
}

########
# Print content
########
# Add on the rendered schema docs (empty string if we don't have it)
if(preg_match('/<!-- params-docs -->/')){
  $content = preg_replace('/<!-- params-docs -->/', $schema_content, $content);
} else {
  $content .= $schema_content;
}
echo '<div class="rendered-markdown">'.$content.'</div>';

########
# Sidebar for everything except the stats page
########
if($pagetab !== 'stats'){
  echo '</div>'; # end of the content div
  echo '<div class="col-lg-4 order-lg-12"><div class="side-sub-subnav sticky-top">';

  # Pipeline homepage & releases - key stats
  if(in_array($pagetab, ['', 'releases'])){
    echo $pipeline_stats_sidebar;
  }
  # Documentation - ToC
  else {
    $toc = '<nav class="toc nav flex-column">'.generate_toc($content).'</nav>';
    # Add on the action buttons for the parameters docs
    if($pagetab == 'usage'){
      $toc .= '<div class="btn-group mt-1" role="group">
                <button class="btn btn-outline-secondary collapse-groups-btn" id="toggle_details" data-toggle="collapse" data-target=".schema-docs-help-text" aria-expanded="false"><i class="fas fa-question-circle mr-1"></i> Toggle help</button>
                <button class="btn btn-outline-secondary collapse-groups-btn" id="show_hidden" data-toggle="collapse" data-target=".param_hidden" aria-expanded="false"><i class="fa mr-1"></i> Show hidden</button>
              </div>';
    }
    echo $toc;
  }
  echo '</div></div>'; # end of the sidebar col
  echo '</div>'; # end of the row
}

include('../includes/footer.php');
