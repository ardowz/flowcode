<?php

use Slim\Http\Request;
use Slim\Http\Response;


//TODO: Move this to proper classes

class DiagramItemBlock {
    public $kind;
    public $labelID;
    public $children;
    public $id;
    public $label;
}

class DiagramNode extends DiagramItemBlock {
    public $nextID;
}

class LineNode extends DiagramItemBlock {
    public $connected; //0 is start and 1 is where its pointing to

}


class FlowCode {

    public $parsedFlowChart;
    public $unorderedFlowChart;
    public $unorderedFlows;

    public $numberOfDiagramNodes = 0;

    public function _construct() {
        $this->parsedFlowChart = [];
        $this->unorderedFlowChart = [];
        $this->unorderedFlows = [];

        $this->stagingNodeEntries = [];
    }

    public function populateLabel($labelID, $label, $parentID)
    {
        foreach ($this->unorderedFlowChart as &$diagramNode) {
            //make sure its for this specific one and its a label
            if ($diagramNode->id == $parentID && in_array($labelID, $diagramNode->children)) {
                $diagramNode->label = $label;
                if (($key = array_search($labelID, $diagramNode->children)) !== false) {
                    unset($diagramNode->children[$key]);
                    if (count($diagramNode->children) == 0) {
                        $diagramNode->children = NULL;
                    }
                }

                return true;
            }
        }
    }

    public function popNodeEntries()
    {

    }

    public function findStart()
    {
        $lineCounter = [];
        foreach ($this->unorderedFlows as $lineNode) {
            foreach ($lineNode->connected as $nodeID) {
                if (isset($lineCounter[$nodeID])) {
                    $lineCounter[$nodeID]++;
                } else {
                    $lineCounter[$nodeID] = 1;
                }
            }
        }

        foreach ($lineCounter as $nodeID => $value) {
            if ($value > 1) {
                continue;
            }

            foreach ($this->unorderedFlows as $lineNode) {
                if ($nodeID == $lineNode->connected[0]) { //start
                    return $nodeID;
                }
            }
        }
    }

    public function findNextIDS($nodeEntry)
    {
        if (!$nodeEntry) {
            return NULL; //end
        }

        $output = [];
        foreach ($this->unorderedFlows as $flowEntry) {
            if ($nodeEntry->id == $flowEntry->connected[0]) {
                $output[] =  $flowEntry->connected[1]; //saving next
            }
        }

        return $output;
    }

    public function getDiagramNodeById($id)
    {
        foreach ($this->unorderedFlowChart as $diagramEntry) {
            if ($diagramEntry->id == $id) {
                return $diagramEntry;
            }
        }
    }

    public function runOrderFlowChart()
    {
        $unorderedCopy = $this->unorderedFlowChart;

        $startID = $this->findStart();

        $orderedEntries = [];

        //separate the lines and the nodes
        $ctr = 0;
        while ($ctr < $this->numberOfDiagramNodes) {
            foreach ($unorderedCopy as $nodeEntry) {
                //only do this step for first entry
                if ($ctr == 0 && $startID == $nodeEntry->id) {
                    $nodeEntry->nextID = $this->findNextIDS($nodeEntry);
                    $orderedEntries[] = $nodeEntry;
                    $ctr++;
                } else if ($ctr > 0 && $ctr < $this->numberOfDiagramNodes){
                    //consecutive entries
                    $nextFlowID = $orderedEntries[$ctr-1];
                    if ($nextFlowID) {
                        if (count($nextFlowID) > 1) {
                            //this is for if statements
                            //TODO: add functionality here
                        } else {
                            $nextNode = $this->getDiagramNodeById($nextFlowID->nextID[0]);
                            if ($nextNode) {
                                $nextNode->nextID = $this->findNextIDS($nextNode);
                                $orderedEntries[] = $nextNode;
                            }

                        }
                    }
                    $ctr++;
                }
            }
        }

        return $orderedEntries;
    }

    public function parseDiagram($diagramData) {
        if (strtolower($diagramData["type"]) == "diagram") {
            $rawDiagramElements = $diagramData["elements"];

            foreach ($rawDiagramElements as $element) {
                unset($node);
                if ($element["type"] == "Node") {
                    //for nodes we need to add them into the the parsed flowchart
                    $node = new DiagramNode();
                    $node->kind = $element["kind"];
                    $node->children = count($element["children"]) > 0 ? $element["children"] : NULL; //if its null then its last
                    $node->labelID = $element["label"];
                    $node->id = $element["id"];
                    $this->unorderedFlowChart[] = $node;
                    $this->numberOfDiagramNodes++;
                } elseif ($element["type"] == "Text") {
                    //this doubles as the text shown and
                    $this->populateLabel($element["id"], $element["label"], $element["parent"]);
                } elseif ($element["type"] == "Edge") {
                    //if its empty, then its invalid
                    if (count($element["connected"]) == 0) {
                        continue;
                    }
                    //line
                    $node = new LineNode();
                    $node->kind = $element["kind"];
                    $node->connected = $element["connected"]; //if its null then its invalid
                    $node->id = $element["id"];
                    $this->unorderedFlows[] = $node;
                }
            }
        } else {
            return false;
        }
    }

    public function generateCodeBlocks($orderedNodeEntries)
    {
        $className = "Flowcode";
        $outputString = <<<PHP
<?php
class $className {

PHP;
        $outputString = htmlspecialchars($outputString);

        $nodeSize = count($orderedNodeEntries) - 1;
        foreach ($orderedNodeEntries as $key => $orderedNodeEntry) {
            if ($key == 0) {
                $nextFunctionName = "call".$orderedNodeEntry->nextID[0];
                $outputString.= <<<PHP
    public function start() {
        echo "Starting".PHP_EOL;
        \$this->$nextFunctionName();
    }
PHP;
//            } else if($key == $nodeSize) {
//                //no need to call any functions
//                break;
            } else {
                //this is where normal functions are being made and triage
//                if ($orderedNodeEntry)
//                var_dump($orderedNodeEntry);
                $currentFunctionName = "call".$orderedNodeEntry->id;
                if ($orderedNodeEntry->nextID) {
                    $nextFunctionName = "call".$orderedNodeEntry->nextID[0];
                } else {
                    $nextFunctionName = NULL;
                }

                //opening the function
                $outputString.= <<<PHP
    
    
    public function $currentFunctionName() {
    
    
PHP;

                if ($orderedNodeEntry->kind == "rectangle") {
                    //this is a process box, so we need to find out what we need to do
                    //figure out what the label says
                    $label = $orderedNodeEntry->label;

                    $commandEndPos = strpos($label, " ");

                    $command = substr($label, 0, $commandEndPos);
                    if ($command == "say") {
                        $message = substr($label, strpos($label, "\"")+1, strpos($label, "\"", strpos($label, "\"")+1));
                        //removing extra "
                        $message = str_replace("\"", '', $message);
                        $phpFunction = <<<PHP
    echo "$message".PHP_EOL;
    
PHP;
                        $outputString.=$phpFunction;

                    }
                }

//closing the function
                if ($nextFunctionName) {
                    $outputString.= <<<PHP
        \$this->$nextFunctionName();
        }
PHP;
                } else {
                    $outputString.= <<<PHP
                    echo "Done Running".PHP_EOL;
}
PHP;
                }
            }
        }


        $outputString.= <<<PHP
        
        }
\$flowCode = new $className();
\$flowCode->start();
PHP;

        return $outputString;
    }
}

// Routes

$app->get("/flowTranslate", function (Request $request, Response $response, array $args) {
    // Sample log message
//    $this->logger->info("Slim-Skeleton '/' route");

    //how to get params
//    $myvar1 = $request->getParam('myvar'); //checks both _GET and _POST [NOT PSR-7 Compliant]
//    $myvar2 = $request->getParsedBody()['myvar']; //checks _POST  [IS PSR-7 compliant]
//    $myvar3 = $request->getQueryParams()['myvar']; //checks _GET [IS PSR-7 compliant]
    // Render index view
    return $this->renderer->render($response, 'index.phtml', $args);
});

$app->post("/flowTranslate", function (Request $request, Response $response, array $args) {
    // Sample log message
    $this->logger->info("Slim-Skeleton '/' route");
//    var_dump($request->getParam("hello"));
    $diagramData = $request->getParsedBody();

    $myfile = fopen("public/requests/req.json", "w") or die("Unable to open file!");
    fwrite($myfile, json_encode($diagramData));
    fclose($myfile);


//    $flowcode = new Flowcode();
//    $flowcode->parseDiagram($diagramData);
//
//    $properlyOrderedEntries = $flowcode->runOrderFlowChart();
//    $exportablCode = $flowcode->generateCodeBlocks($properlyOrderedEntries);
//    var_export($exportablCode);
    exit();


//    $jsonDiagram = json_decode($diagramData);
//    var_dump($diagramData);


    // Render index view
//    return $this->renderer->render($response, 'index.phtml', $args);
});

$app->get('/standbyPost', function (Request $request, Response $response, array $args) {
    // Sample log message
    $this->logger->info("Slim-Skeleton '/' route");

    $myfile = @fopen("public/requests/req.json", "r") or false;
    if ($myfile) {
        if (filesize("public/requests/req.json") > 0) {
            $contents = fread($myfile, filesize("public/requests/req.json"));
            fclose($myfile);

            $diagramData = json_decode($contents, true);

            $flowcode = new Flowcode();
            $flowcode->parseDiagram($diagramData);

            $properlyOrderedEntries = $flowcode->runOrderFlowChart();
            $exportablCode = $flowcode->generateCodeBlocks($properlyOrderedEntries);
        }
    }

    // Render index view
    return $this->renderer->render($response, 'index.phtml', [
        'code' => $exportablCode
    ]);
});

$app->get('/clearPost', function (Request $request, Response $response, array $args) {
    // Sample log message
    $this->logger->info("Slim-Skeleton '/' route");

    $myfile = @fopen("public/requests/req.json", "w") or false;
    if ($myfile) {
        //empty the file
        fwrite($myfile, "");
        fclose($myfile);
    }

    // Render index view
    return $this->renderer->render($response, 'index.phtml', $args);
});

$app->get('/[{name}]', function (Request $request, Response $response, array $args) {
    // Sample log message
    $this->logger->info("Slim-Skeleton '/' route");

    // Render index view
    return $this->renderer->render($response, 'index.phtml', $args);
});
