<?php
// Build the HTML for a pipeline documentation page.
// Imported by public_html/pipeline.php - pulls a markdown file from GitHub and renders.

# Details for parsing markdown file, fetched from Github
# Build the remote file path
# Special case - root docs is allow
require_once('../includes/parse_md.php');
if(substr($_SERVER['REQUEST_URI'], -3) == '.md'){
  # Clean up URL by removing .md
  header('Location: '.substr($_SERVER['REQUEST_URI'], 0, -3));
  exit;
}
$filename = 'docs/usage.md';
$md_trim_before = '# Introduction';

# Build the local and remote file paths based on whether we have a release or not
if($release !== 'dev'){
  $git_branch = $release;
  $local_fn_base = dirname(dirname(dirname(__FILE__)))."/markdown/pipelines/".$pipeline->name."/".$release."/";
} else {
  $git_branch = 'dev';
  $local_fn_base = dirname(dirname(dirname(__FILE__)))."/markdown/pipelines/".$pipeline->name."/".$pipeline->pushed_at."/";
}
$local_md_fn = $local_fn_base.$filename;
$markdown_fn = 'https://raw.githubusercontent.com/'.$pipeline->full_name.'/'.$git_branch.'/'.$filename;

# Check if we have a local copy of the markdown file and fetch if not
if(file_exists($local_md_fn)){
  $markdown_fn = $local_md_fn;
} else {
  # Build directories if needed
  if (!is_dir(dirname($local_md_fn))) {
    mkdir(dirname($local_md_fn), 0777, true);
  }
  $md_contents = file_get_contents($markdown_fn);
  if($md_contents){
    file_put_contents($local_md_fn, $md_contents);
    $markdown_fn = $local_md_fn;
  }
}
# Try to fetch the nextflow_schema.json file for the latest release, taken from pipeline.php
$gh_launch_schema_fn = dirname(dirname(__FILE__))."/api_cache/json_schema/{$pipeline->name}/{$release}.json";
$gh_launch_no_schema_fn = dirname(dirname(__FILE__))."/api_cache/json_schema/{$pipeline->name}/{$release}.NO_SCHEMA";
# Build directories if needed
if (!is_dir(dirname($gh_launch_schema_fn))) {
  mkdir(dirname($gh_launch_schema_fn), 0777, true);
}
// Load cache if not 'dev'
if((!file_exists($gh_launch_schema_fn) && !file_exists($gh_launch_no_schema_fn)) || $release == 'dev'){
  $api_opts = stream_context_create([ 'http' => [ 'method' => 'GET', 'header' => [ 'User-Agent: PHP' ] ] ]);
  $gh_launch_schema_url = "https://raw.githubusercontent.com/nf-core/{$pipeline->name}/{$release}/nextflow_schema.json";
  $gh_launch_schema_json = file_get_contents($gh_launch_schema_url, false, $api_opts);
  if(!in_array("HTTP/1.1 200 OK", $http_response_header)){
    # Remember for next time
    file_put_contents($gh_launch_no_schema_fn, '');
  } else {
    # Save cache
    file_put_contents($gh_launch_schema_fn, $gh_launch_schema_json);
  }
}
if(file_exists($gh_launch_schema_fn)){

// Markdown parsing libraries


$raw_json = file_get_contents($gh_launch_schema_fn);
$schema = json_decode($raw_json, TRUE);

$schema_content = '<div class="schema-docs">'.add_ids_to_headers('<h1>Parameters</h1>');

  foreach($schema["properties"] as $k=>$v){
    // for loop through top level items
      $fa_icon='<i class="fa-icons fa-fw mr-2 text-muted"></i> ';
      $required_label = '';
      if($v["type"]=="object"){
        if(array_key_exists("fa_icon", $v)){
          $fa_icon = '<i class="'.$v['fa_icon'].' fa-fw mr-2"></i> ';
        }
        $schema_content .= '<div class="d-flex justify-content-between align-items-center">';


        $schema_content .= add_ids_to_headers('<h2><a>'.$fa_icon.$k.'</a></h2>').$required_label;
        $schema_content .='</div>';
        if(array_key_exists("description", $v)){
        $schema_content.='<p class="lead">'.$v['description'].'</p>';
        }
        foreach($v["properties"] as $kk=>$vv){
          // for loop through each param in a group
          $fa_icon='<i class="fa-icons fa-fw ml-0 text-muted"></i> ';
          $hidden_label ='';
          $required_label = '';
          if(array_key_exists("fa_icon", $vv)){
            $fa_icon = '<i class="'.$vv['fa_icon'].' fa-fw ml-0"></i> ';
          }
          if(array_key_exists("hidden", $vv) && $vv["hidden"]){
            $hidden_label = '<div><span class="badge badge-secondary">hidden</span></div>';
          }
          if(array_key_exists("required", $v) && in_array($kk,$v["required"])){
            $required_label = '<div><span class="badge badge-warning">required</span></div>';
          }
          $schema_content.='<div class="d-flex justify-content-between align-items-center">';
          if($hidden_label){//lower the visibility of hidden parameters
            $schema_content.=add_ids_to_headers('<h6>'.$fa_icon.'--'.$kk.'</h6>').$hidden_label.$required_label.'</div>';
          } else {
            $schema_content.=add_ids_to_headers('<h3>'.$fa_icon.'<code>--'.$kk.'</code></h3>').$required_label.'</div>';
          }
          if(!$hidden_label){
            if(array_key_exists("description", $vv) && $vv["description"]!=""){
              $schema_content.='<div class="row"><span class="schema-docs-description col-10">'.parse_md($vv['description']).'</span>';
              if(array_key_exists("help_text", $vv) && $vv["help_text"]!=""  ){
                $help_text = parse_md($vv['help_text']);
                if(strlen($help_text)>150){ // collapse long help texts
                  $schema_content.='<div class="col-2">';
                  $schema_content.='<button class="btn btn-sm btn-outline-secondary" data-toggle="collapse" href="#'.preg_replace("/\/|\s/","-",$kk).'-help" aria-expanded="false"><i class="fas fa-question-circle"></i> Help</button>';
                  $schema_content.='</div>';
                  $schema_content.='</div><div class="collapse card schema-docs-help-text" id="'.preg_replace("/\/|\s/","-",$kk).'-help"><div class="card-body small text-muted">'.$help_text.'</div></div>';
                }else{
                  $schema_content.='</div><span class="schema-docs-help-text">'.$help_text.'</span>';
                }
              }else{
                $schema_content.='</div>';
              }
            }
          } else { // collapse description and help text for hidden params
            if(array_key_exists("description", $vv) && $vv["description"]!=""){
              $schema_content.='<div class="collapse param_hidden">';
              $schema_content.='<div class="row"><span class="schema-docs-description col-10">'.parse_md($vv['description']).'</span>';
              if(array_key_exists("help_text", $vv) && $vv["help_text"]!=""  ){
                $help_text = parse_md($vv['help_text']);
                $schema_content.='</div><span class="schema-docs-help-text">'.$help_text.'</span>';
              }else{
                $schema_content.='</div>';
              }
              $schema_content.='</div>';
            }
          }
        }
      }else{
        //top level parameter
        if(array_key_exists("fa_icon", $v)){
          $fa_icon = '<i class="'.$v['fa_icon'].' fa-fw mr-1"></i> ';
        }
        $schema_content.='<h4 class="text-secondary">' . $fa_icon .'<code>--'.$vv.'</code></h4>';
        '<h4 class="text-secondary">' . $fa_icon .'<code>--'.$vv.'</code></h4>';
      }
  }
  $schema_content .= '</div>';
}
# Configs to make relative URLs work
$src_url_prepend = 'https://raw.githubusercontent.com/'.$pipeline->full_name.'/'.$git_branch.'/'.implode('/', array_slice($path_parts, 1, -1)).'/';
$href_url_prepend = '/'.$pipeline->name.'/'.implode('/', array_slice($path_parts, 1)).'/';
$href_url_prepend = preg_replace('/\/\/+/', '/', $href_url_prepend);
$href_url_suffix_cleanup = '\.md';

$md_content_replace = array(
    array(''),
    array('# ')
);

# Styling
$md_content_replace = array(
    array('# nf-core/'.$pipeline->name.': '),
    array('# ')
);
$html_content_replace = array(
    array('<table>'),
    array('<table class="table">')
);

# Header - keywords
$header_html = '<p class="mb-0">';
foreach($pipeline->topics as $keyword){
  $header_html .= '<a href="/pipelines?q='.$keyword.'" class="badge pipeline-topic">'.$keyword.'</a> ';
}
$header_html .= '</p>';

// Highlight any search terms if we have them
if(isset($_GET['q']) && strlen($_GET['q'])){
  $title = preg_replace("/(".$_GET['q'].")/i", "<mark>$1</mark>", $title);
  $subtitle = preg_replace("/(".$_GET['q'].")/i", "<mark>$1</mark>", $subtitle);
  $header_html = preg_replace("/(".$_GET['q'].")/i", "<mark>$1</mark>", $header_html);
}

// Footer source link
$md_github_url = 'https://github.com/'.$pipeline->full_name.'/blob/'.$git_branch.'/'.$filename;


// Content will be rendered by header.php
