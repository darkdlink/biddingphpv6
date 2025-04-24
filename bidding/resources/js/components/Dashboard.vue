<template>
    <div class="dashboard">
      <header class="header">
        <h1>Bidding - Sistema de Capitalização de Licitações</h1>
        <div class="user-info">
          <span>{{ user.name }}</span>
          <button @click="logout">Sair</button>
        </div>
      </header>

      <div class="dashboard-content">
        <aside class="sidebar">
          <nav>
            <ul>
              <li><router-link to="/dashboard">Dashboard</router-link></li>
              <li><router-link to="/bids">Licitações</router-link></li>
              <li><router-link to="/proposals">Propostas</router-link></li>
              <li><router-link to="/suppliers">Fornecedores</router-link></li>
              <li><router-link to="/reports">Relatórios</router-link></li>
              <li><router-link to="/scraper">Importar Licitações</router-link></li>
            </ul>
          </nav>
        </aside>

        <main class="main-content">
          <div class="stats-grid">
            <div class="stat-card">
              <h3>Licitações Ativas</h3>
              <div class="stat-value">{{ stats.activeBids }}</div>
              <div class="stat-change" :class="stats.bidsChange > 0 ? 'positive' : 'negative'">
                {{ stats.bidsChange > 0 ? '+' : '' }}{{ stats.bidsChange }}% esta semana
              </div>
            </div>

            <div class="stat-card">
              <h3>Propostas Enviadas</h3>
              <div class="stat-value">{{ stats.proposals }}</div>
              <div class="stat-change" :class="stats.proposalsChange > 0 ? 'positive' : 'negative'">
                {{ stats.proposalsChange > 0 ? '+' : '' }}{{ stats.proposalsChange }}% esta semana
              </div>
            </div>

            <div class="stat-card">
              <h3>Taxa de Sucesso</h3>
              <div class="stat-value">{{ stats.successRate }}%</div>
              <div class="stat-change" :class="stats.successRateChange > 0 ? 'positive' : 'negative'">
                {{ stats.successRateChange > 0 ? '+' : '' }}{{ stats.successRateChange }}% esta semana
              </div>
            </div>

            <div class="stat-card">
              <h3>Valor Total</h3>
              <div class="stat-value">{{ formatCurrency(stats.totalValue) }}</div>
              <div class="stat-change" :class="stats.valueChange > 0 ? 'positive' : 'negative'">
                {{ stats.valueChange > 0 ? '+' : '' }}{{ stats.valueChange }}% esta semana
              </div>
            </div>
          </div>

          <div class="chart-container">
            <h2>Licitações por Categoria</h2>
            <div class="chart">
              <canvas ref="categoryChart"></canvas>
            </div>
          </div>

          <div class="recent-bids">
            <div class="section-header">
              <h2>Licitações Recentes</h2>
              <router-link to="/bids" class="view-all">Ver todas</router-link>
            </div>

            <table>
              <thead>
                <tr>
                  <th>Número</th>
                  <th>Título</th>
                  <th>Valor Estimado</th>
                  <th>Data Limite</th>
                  <th>Status</th>
                  <th>Ações</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="bid in recentBids" :key="bid.id">
                  <td>{{ bid.bid_number }}</td>
                  <td>{{ bid.title }}</td>
                  <td>{{ formatCurrency(bid.estimated_value) }}</td>
                  <td>{{ formatDate(bid.closing_date) }}</td>
                  <td>
                    <span class="status" :class="getStatusClass(bid.status)">
                      {{ bid.status }}
                    </span>
                  </td>
                  <td>
                    <button @click="viewBid(bid.id)" class="btn-view">Ver</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="alerts">
            <h2>Alertas</h2>
            <div v-if="alerts.length === 0" class="no-alerts">
              Não há alertas pendentes
            </div>
            <div v-else class="alert-list">
              <div v-for="alert in alerts" :key="alert.id" class="alert-item" :class="alert.priority">
                <div class="alert-icon">
                  <i :class="getAlertIcon(alert.type)"></i>
                </div>
                <div class="alert-content">
                  <h4>{{ alert.title }}</h4>
                  <p>{{ alert.message }}</p>
                  <span class="alert-date">{{ formatDate(alert.created_at) }}</span>
                </div>
                <button @click="dismissAlert(alert.id)" class="btn-dismiss">×</button>
              </div>
            </div>
          </div>
        </main>
      </div>
    </div>
  </template>

  <script>
  import axios from 'axios';
  import Chart from 'chart.js/auto';

  export default {
    data() {
      return {
        user: {
          name: 'Administrador',
          role: 'admin'
        },
        stats: {
          activeBids: 0,
          bidsChange: 0,
          proposals: 0,
          proposalsChange: 0,
          successRate: 0,
          successRateChange: 0,
          totalValue: 0,
          valueChange: 0
        },
        recentBids: [],
        alerts: [],
        categoryChart: null
      };
    },
    mounted() {
      this.fetchDashboardData();
      this.fetchRecentBids();
      this.fetchAlerts();
    },
    methods: {
      async fetchDashboardData() {
        try {
          const response = await axios.get('/api/dashboard/stats');
          this.stats = response.data;
          this.initCategoryChart(response.data.categoriesData);
        } catch (error) {
          console.error('Erro ao buscar dados do dashboard:', error);
        }
      },
      async fetchRecentBids() {
        try {
          const response = await axios.get('/api/bids/recent');
          this.recentBids = response.data;
        } catch (error) {
          console.error('Erro ao buscar licitações recentes:', error);
        }
      },
      async fetchAlerts() {
        try {
          const response = await axios.get('/api/alerts');
          this.alerts = response.data;
        } catch (error) {
          console.error('Erro ao buscar alertas:', error);
        }
      },
      initCategoryChart(categoriesData) {
        const ctx = this.$refs.categoryChart.getContext('2d');

        if (this.categoryChart) {
          this.categoryChart.destroy();
        }

        this.categoryChart = new Chart(ctx, {
          type: 'pie',
          data: {
            labels: categoriesData.map(item => item.name),
            datasets: [{
              data: categoriesData.map(item => item.count),
              backgroundColor: [
                '#4F81BD', '#C0504D', '#9BBB59', '#8064A2', '#4BACC6',
                '#F79646', '#B2A1C7', '#92CDDC', '#FAC090', '#A8D08D'
              ]
            }]
          },
          options: {
            responsive: true,
            plugins: {
              legend: {
                position: 'right'
              }
            }
          }
        });
      },
      formatCurrency(value) {
        if (!value) return 'R$ 0,00';
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
      getStatusClass(status) {
        const statusMap = {
          'Novo': 'new',
          'Em andamento': 'ongoing',
          'Finalizado': 'finished',
          'Cancelado': 'canceled',
          'Vencido': 'expired'
        };

        return statusMap[status] || 'default';
      },
      getAlertIcon(type) {
        const iconMap = {
          'deadline': 'fa fa-clock',
          'opportunity': 'fa fa-lightbulb',
          'warning': 'fa fa-exclamation-triangle',
          'system': 'fa fa-cog'
        };

        return iconMap[type] || 'fa fa-bell';
      },
      viewBid(id) {
        this.$router.push(`/bids/${id}`);
      },
      async dismissAlert(id) {
        try {
          await axios.post(`/api/alerts/${id}/dismiss`);
          this.alerts = this.alerts.filter(alert => alert.id !== id);
        } catch (error) {
          console.error('Erro ao dispensar alerta:', error);
        }
      },
      logout() {
        // Implemente a lógica de logout
        axios.post('/api/auth/logout')
          .then(() => {
            this.$router.push('/login');
          })
          .catch(error => {
            console.error('Erro ao fazer logout:', error);
          });
      }
    }
  };
  </script>

  <style scoped>
  .dashboard {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
  }

  .header {
    background-color: #1e3a8a;
    color: white;
    padding: 1rem 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  .user-info {
    display: flex;
    align-items: center;
    gap: 1rem;
  }

  .dashboard-content {
    display: flex;
    flex: 1;
  }

  .sidebar {
    width: 250px;
    background-color: #f0f4f8;
    padding: 2rem 0;
  }

  .sidebar ul {
    list-style: none;
    padding: 0;
    margin: 0;
  }

  .sidebar li {
    margin-bottom: 0.5rem;
  }

  .sidebar a {
    display: block;
    padding: 0.75rem 2rem;
    color: #334155;
    text-decoration: none;
    transition: all 0.3s ease;
  }

  .sidebar a:hover, .sidebar a.router-link-active {
    background-color: #dbeafe;
    color: #1e40af;
  }

  .main-content {
    flex: 1;
    padding: 2rem;
    background-color: #f8fafc;
    overflow-y: auto;
  }

  .stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
  }

  .stat-card {
    background-color: white;
    border-radius: 0.5rem;
    padding: 1.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  }

  .stat-card h3 {
    margin: 0 0 0.5rem 0;
    color: #64748b;
    font-size: 1rem;
    font-weight: 500;
  }

  .stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    color: #334155;
  }

  .stat-change {
    font-size: 0.875rem;
  }

  .stat-change.positive {
    color: #10b981;
  }

  .stat-change.negative {
    color: #ef4444;
  }

  .chart-container {
    background-color: white;
    border-radius: 0.5rem;
    padding: 1.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    margin-bottom: 2rem;
  }

  .chart {
    height: 300px;
  }

  .section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
  }

  .view-all {
    color: #3b82f6;
    text-decoration: none;
  }

  table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 2rem;
  }

  thead {
    background-color: #f1f5f9;
  }

  th, td {
    padding: 0.75rem 1rem;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
  }

  .status {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    font-weight: 500;
  }

  .status.new {
    background-color: #dbeafe;
    color: #1e40af;
  }

  .status.ongoing {
    background-color: #fef3c7;
    color: #92400e;
  }

  .status.finished {
    background-color: #d1fae5;
    color: #065f46;
  }

  .status.canceled {
    background-color: #fee2e2;
    color: #b91c1c;
  }

  .status.expired {
    background-color: #f3f4f6;
    color: #6b7280;
  }

  .btn-view {
    background-color: #3b82f6;
    color: white;
    border: none;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    cursor: pointer;
  }

  .alerts {
    background-color: white;
    border-radius: 0.5rem;
    padding: 1.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  }

  .no-alerts {
    color: #64748b;
    font-style: italic;
    text-align: center;
    padding: 2rem 0;
  }

  .alert-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
  }

  .alert-item {
    display: flex;
    padding: 1rem;
    border-radius: 0.5rem;
    border-left: 4px solid;
  }

  .alert-item.high {
    border-color: #ef4444;
    background-color: #fee2e2;
  }

  .alert-item.medium {
    border-color: #f59e0b;
    background-color: #fef3c7;
  }

  .alert-item.low {
    border-color: #3b82f6;
    background-color: #dbeafe;
  }

  .alert-icon {
    margin-right: 1rem;
    font-size: 1.25rem;
  }

  .alert-content {
    flex: 1;
  }

  .alert-content h4 {
    margin: 0 0 0.25rem 0;
  }

  .alert-content p {
    margin: 0 0 0.5rem 0;
  }

  .alert-date {
    font-size: 0.75rem;
    color: #64748b;
  }

  .btn-dismiss {
    background: none;
    border: none;
    font-size: 1.25rem;
    color: #64748b;
    cursor: pointer;
  }
  </style>
