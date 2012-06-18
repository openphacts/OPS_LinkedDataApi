<?php if ($listEndpoints = $ConfigGraph->getListEndpoints()): ?>
      <ul>
      <?php foreach ($listEndpoints as $endpointUri): ?>
          <?php $ConfigGraph->resetApiAndEndpoint($apiUri, $endpointUri); ?>
  <li>
      <h3>
	  <?php $endpointName = $ConfigGraph->get_first_literal($endpointUri, API.'name') ;
            echo $endpointName ;?>
           <em class="type rdf-type">List Endpoint</em>
      </h3>
      <dl class="endpoint-properties">
            <?php if ($exampleRequestPaths = $ConfigGraph->get_literal_triple_values($endpointUri, API.'exampleRequestPath')): ?>
        <dt>Example URIs:</dt>
        <dd>
            <ul>
            <?php foreach ($exampleRequestPaths as $exampleRequestPath): ?>
            <li>
                <a href="<?php echo $Request->getInstallSubDir().$exampleRequestPath ?>"><?php echo $exampleRequestPath ?></a>
            </li>
            <?php endforeach ?>
            </ul>
        </dd>
            <?php endif ?>      
            <dt>selector filters</dt>                
            <dd>
                <code><?php echo htmlentities($ConfigGraph->getSelectFilter()) ?></code>
            </dd>
            <dt>inherited selector filters</dt>                
            <dd>
                <?php foreach ($ConfigGraph->getInheritedSelectFilters() as $filter): ?>
<code><?php echo htmlentities($filter) ?></code>
                <?php endforeach ?>
            </dd>
<!--Antonis <?php if ($where = $ConfigGraph->getSelectWhere()): ?>
            <dt>Selector <code>WHERE</code></dt>
            <dd>
                <pre>
                    <code>
<?php echo htmlentities($where) ?>
                    </code>
                </pre>
            </dd>
            <?php endif ?> -->
            <?php if ($viewers=$ConfigGraph->get_resource_triple_values($endpointUri, API.'viewer')) :?>
        <dt>Viewers:</dt>
        <dd class="viewers">
            <ul>
            <?php foreach ($viewers as $viewerUri): ?>
                <li>
                    <h4><?php echo $ConfigGraph->get_first_literal($viewerUri, API.'name') ?> 
                        <?php if ($viewerUri == $ConfigGraph->get_first_resource($endpointUri, API.'defaultViewer')): ?>
                            <em>default viewer</em>
                        <?php endif ?>
                        </h4>
                    <dl>
                        <?php if ($apiProperties = $ConfigGraph->get_resource_triple_values($viewerUri, API.'property')): ?>
                        <dt>Properties:</dt>
                        <dd class="properties">
                            <ul>
                        <?php foreach ($apiProperties as $propUri): ?>
                            <li><a href="<?php echo $propUri ?>"><?php echo $ConfigGraph->get_label($propUri)?></a></li>
                        <?php endforeach ?>                                        
                        </ul>
                        </dd>
                        <?php endif ?>


                    </dl>
                </li>
            <?php endforeach ?>
            </ul>
        </dd>
      <?php endif?>
      <?php if ($defaultViewer = $ConfigGraph->get_first_resource($endpointUri, API.'defaultViewer')): ?>
          <dt>Default Viewer</dt>
          <dd>
            <?php echo $ConfigGraph->get_label($defaultViewer) ?>
            <?php if (!$ConfigGraph->has_triples_about($defaultViewer)): ?>
                <p class="warning">
                    There is no description of this viewer ( <?php echo $defaultViewer ?> ) in the api config files; it will not work. 
                </p>
            <?php endif ?>
<!-- Antonis -->
                <?php if ($template = $ConfigGraph->get_literal_triple_values($defaultViewer, API.'template')): ?>
                  <dt>Response Template:</dt>
                  <dd>
                  <pre><code>
<?php echo htmlentities($template[0]) ?>
                  </code></pre>
                  </dd>
                  <?php endif ?>
          </dd>
      <?php endif ?>
          
      </dl>
  </li>
<?php endforeach ?>
</ul>
<?php endif ?>
