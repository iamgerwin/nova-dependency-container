import IndexField from './components/IndexField.vue';
import DetailField from './components/DetailField.vue';
import FormField from './components/FormField.vue';

Nova.booting((app, store) => {
  app.component('index-nova-dependency-container', IndexField);
  app.component('detail-nova-dependency-container', DetailField);
  app.component('form-nova-dependency-container', FormField);
});