import React from 'react';
import { z } from 'zod';
import { createLibrary, defineComponent } from '@openuidev/react-lang';
import { createParser, jsonToOpenUI } from '@openuidev/lang-core';
export const DrupalComponent = defineComponent({
  name: 'DrupalComponent',
  description: 'An installed Drupal SDC. Drupal renders its original template and assets.',
  props: z.object({ component: z.string(), props: z.record(z.string(), z.unknown()), slots: z.record(z.string(), z.array(z.any())) }),
  component: () => null,
});
export function makeLibrary(Editor) {
  const Page = defineComponent({
    name: 'Page', description: 'An ordered composition of Drupal components, editing controls and a native preview.',
    props: z.object({ children: z.array(DrupalComponent.ref) }),
    component: ({ props }) => React.createElement(Editor, { tree: fromElements(props.children) }),
  });
  return createLibrary({ components: [Page, DrupalComponent], root: 'Page', id: 'drupal-components' });
}
function fromElements(nodes, depth = 0) {
  if (depth > 12 || !Array.isArray(nodes)) throw new Error('Invalid component nesting.');
  return nodes.map((node) => {
    if (!node || node.typeName !== 'DrupalComponent' || node.partial) throw new Error('Use complete DrupalComponent elements.');
    return { component: node.props.component, props: node.props.props, slots: Object.fromEntries(Object.entries(node.props.slots).map(([name, children]) => [name, fromElements(children, depth + 1)])) };
  });
}
export function parseComposition(program, library) {
  const result = createParser(library.toJSONSchema()).parse(program);
  if (result.meta.incomplete || result.meta.unresolved.length || result.meta.errors.length || result.queryStatements.length || result.mutationStatements.length || Object.keys(result.stateDeclarations).length || !result.root || result.root.typeName !== 'Page') throw new Error('Use a complete Page composition with static DrupalComponent props and named slots.');
  return fromElements(result.root.props.children);
}
export function toProgram(tree, library) {
  const element = (node) => ({ type: 'element', typeName: 'DrupalComponent', partial: false, props: { component: node.component, props: node.props, slots: Object.fromEntries(Object.entries(node.slots || {}).map(([slot, children]) => [slot, children.map(element)])) } });
  return jsonToOpenUI({ type: 'element', typeName: 'Page', partial: false, props: { children: tree.map(element) } }, library);
}
export function flatten(tree, base = [], depth = 0) {
  return tree.flatMap((node, i) => {
    const path = [...base, i];
    return [{ node, path, depth }, ...Object.entries(node.slots).flatMap(([slot, children]) => flatten(children, [...path, 'slots', slot], depth + 1))];
  });
}
export function updateProp(tree, path, name, value) {
  const copy = structuredClone(tree);
  const node = path.reduce((parent, key) => parent[key], copy);
  if (value === undefined) delete node.props[name]; else node.props[name] = value;
  return copy;
}
