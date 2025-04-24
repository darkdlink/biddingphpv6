<template>
    <div class="bid-list">
      <h1>Licitações Disponíveis</h1>

      <div class="filters">
        <input v-model="search" placeholder="Buscar licitações..." />
        <select v-model="categoryFilter">
          <option value="">Todas as categorias</option>
          <option v-for="category in categories" :key="category.id" :value="category.id">
            {{ category.name }}
          </option>
        </select>
      </div>

      <table>
        <thead>
          <tr>
            <th>Número</th>
            <th>Título</th>
            <th>Categoria</th>
            <th>Valor Estimado</th>
            <th>Data de Abertura</th>
            <th>Data de Fechamento</th>
            <th>Status</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="bid in filteredBids" :key="bid.id">
            <td>{{ bid.bid_number }}</td>
            <td>{{ bid.title }}</td>
            <td>{{ bid.category.name }}</td>
            <td>{{ formatCurrency(bid.estimated_value) }}</td>
            <td>{{ formatDate(bid.opening_date) }}</td>
            <td>{{ formatDate(bid.closing_date) }}</td>
            <td>{{ bid.status }}</td>
            <td>
              <button @click="viewBid(bid.id)">Visualizar</button>
              <button @click="createProposal(bid.id)">Nova Proposta</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </template>

  <script>
  import axios from 'axios';

  export default {
    data() {
      return {
        bids: [],
        categories: [],
        search: '',
        categoryFilter: '',
      };
    },
    computed: {
      filteredBids() {
        return this.bids.filter(bid => {
          const matchesSearch = bid.title.toLowerCase().includes(this.search.toLowerCase()) ||
                               bid.bid_number.toLowerCase().includes(this.search.toLowerCase());
          const matchesCategory = !this.categoryFilter || bid.bid_category_id == this.categoryFilter;

          return matchesSearch && matchesCategory;
        });
      }
    },
    created() {
      this.fetchBids();
      this.fetchCategories();
    },
    methods: {
      async fetchBids() {
        try {
          const response = await axios.get('/api/bids');
          this.bids = response.data;
        } catch (error) {
          console.error('Error fetching bids:', error);
        }
      },
      async fetchCategories() {
        try {
          const response = await axios.get('/api/bid-categories');
          this.categories = response.data;
        } catch (error) {
          console.error('Error fetching categories:', error);
        }
      },
      formatCurrency(value) {
        if (!value) return 'N/A';
        return new Intl.NumberFormat('pt-BR', {
          style: 'currency',
          currency: 'BRL'
        }).format(value);
      },
      formatDate(dateString) {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        return new Intl.DateTimeFormat('pt-BR').format(date);
      },
      viewBid(id) {
        this.$router.push(`/bids/${id}`);
      },
      createProposal(id) {
        this.$router.push(`/proposals/new?bid_id=${id}`);
      }
    }
  };
  </script>
