<script lang="ts">
    import {
        Background,
        BackgroundVariant,
        Controls,
        MiniMap,
        Panel,
        SvelteFlow,
    } from '@xyflow/svelte';
    import type { Connection, Edge, Node } from '@xyflow/svelte';
    import '@xyflow/svelte/dist/style.css';
    import Plus from 'lucide-svelte/icons/plus';
    import Sparkles from 'lucide-svelte/icons/sparkles';
    import { toast } from 'svelte-sonner';
    import { Button } from '@/components/ui/button';
    import type { EditorState } from '@/lib/lecturn/editor-state.svelte';
    import SlideNode from './SlideNode.svelte';
    import TransitionNode from './TransitionNode.svelte';

    let {
        editor,
        onOpenSlide,
    }: {
        editor: EditorState;
        onOpenSlide: (slideIndex: number) => void;
    } = $props();

    const nodeTypes = { slide: SlideNode, transition: TransitionNode };

    const slideExcerpt = (slideId: string | undefined): string => {
        const slide = editor.content.slides.find(
            (candidate) => candidate.id === slideId,
        );

        for (const blocks of Object.values(slide?.slots ?? {})) {
            for (const block of blocks) {
                if (block.type === 'text' && block.content.trim() !== '') {
                    return block.content;
                }
            }
        }

        return '';
    };

    // The domain graph (editor.flow) is authoritative; xyflow gets throwaway
    // view arrays rebuilt from it so its internal fields (selected, measured,
    // …) never leak into what we persist.
    let nodes = $state.raw<Node[]>([]);
    let edges = $state.raw<Edge[]>([]);

    $effect(() => {
        nodes = editor.flow.nodes.map((node): Node => {
            const slideIndex = editor.content.slides.findIndex(
                (slide) => slide.id === node.data.slideId,
            );

            return {
                id: node.id,
                type: node.type,
                position: { ...node.position },
                data:
                    node.type === 'slide'
                        ? {
                              index: slideIndex,
                              title:
                                  editor.content.slides[slideIndex]?.title ??
                                  null,
                              enabled: node.data.slideId
                                  ? editor.isSlideEnabled(node.data.slideId)
                                  : true,
                              excerpt: slideExcerpt(node.data.slideId),
                              onOpen: () => onOpenSlide(slideIndex),
                          }
                        : {
                              label: node.data.label ?? null,
                              placeholder: editor.transitionPlaceholder(
                                  node.id,
                              ),
                              onLabelChange: (
                                  nodeId: string,
                                  label: string | null,
                              ) => {
                                  if (
                                      !editor.setTransitionLabel(nodeId, label)
                                  ) {
                                      toast.error(
                                          'Another step on this slide already has that name.',
                                      );
                                  }
                              },
                          },
            };
        });

        edges = editor.flow.edges.map((edge): Edge => ({
            id: edge.id,
            source: edge.source,
            target: edge.target,
            animated:
                editor.flow.nodes.find((node) => node.id === edge.target)
                    ?.type === 'transition',
        }));
    });

    const handleConnect = (connection: Connection): void => {
        if (!editor.connect(connection.source, connection.target)) {
            toast.error('That connection is not allowed.');
            // Reassignment drops the edge xyflow optimistically added.
            edges = [...edges];
        }
    };

    const handleDelete = ({
        nodes: deletedNodes,
        edges: deletedEdges,
    }: {
        nodes: Node[];
        edges: Edge[];
    }): void => {
        for (const edge of deletedEdges) {
            editor.removeEdge(edge.id);
        }

        for (const node of deletedNodes) {
            editor.removeFlowNode(node.id);
        }
    };

    const handleDragStop = ({
        nodes: draggedNodes,
    }: {
        nodes: Node[];
    }): void => {
        for (const node of draggedNodes) {
            editor.moveNode(node.id, node.position);
        }
    };

    const viewportCenter = (): { x: number; y: number } => ({
        x: 80 + Math.random() * 160,
        y: 80 + Math.random() * 160,
    });
</script>

<div class="h-full w-full" data-test="flow-canvas">
    <SvelteFlow
        bind:nodes
        bind:edges
        {nodeTypes}
        fitView
        colorMode="system"
        onconnect={handleConnect}
        ondelete={handleDelete}
        onnodedragstop={handleDragStop}
    >
        <Panel position="top-left" class="flex gap-2">
            <Button
                variant="outline"
                size="sm"
                onclick={() => editor.addSlideNodeAt(viewportCenter())}
                data-test="flow-add-slide"
            >
                <Plus class="h-4 w-4" /> Slide
            </Button>
            <Button
                variant="outline"
                size="sm"
                onclick={() => editor.addTransitionNode(viewportCenter())}
                data-test="flow-add-transition"
            >
                <Sparkles class="h-4 w-4" /> Transition
            </Button>
        </Panel>
        <Controls />
        <MiniMap />
        <Background variant={BackgroundVariant.Dots} gap={12} size={1} />
    </SvelteFlow>
</div>
