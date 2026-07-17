# Flow <~> Transitions builder

- Use svelte flow to organize slides
- create custom nodes for Transitions vs slide nodes
- On the Slide composer, every "block" should be assignable a key/id/name/slug that ties it to a node
- The nodes will map to Animotion's <Transition> component or <Action> component, and make use of the `phase` prop to link what happens when
