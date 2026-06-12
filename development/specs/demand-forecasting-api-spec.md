# Demand Forecasting API Specification

## Overview

The Demand Forecasting system provides AI-powered sales predictions, reorder suggestions, and pattern analysis. It uses historical sales data combined with Ollama LLM for intelligent insights.

**Base URL:** `/api/v1/mobile/forecast`
**Authentication:** Bearer Token (Passport)

---

## Endpoints

### 1. Daily Sales Forecast

Predicts sales for the next N days based on historical patterns.

**Endpoint:** `GET /forecast/daily-sales`

**Query Parameters:**
| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `days` | integer | No | 7 | Number of days to forecast (1-30) |
| `store_id` | integer | No | null | Filter by specific store |
| `refresh` | boolean | No | false | Force regenerate forecast |

**Response:**
```json
{
  "success": true,
  "data": {
    "forecasts": [
      {
        "date": "2026-02-02",
        "day": "Monday",
        "predicted_sales": 125639.78,
        "confidence": 71.68,
        "lower_bound": 59740.24,
        "upper_bound": 191539.32,
        "factors": {
          "day_of_week": "Monday",
          "based_on_samples": 4,
          "overall_avg": 142786.52
        }
      }
    ],
    "total_predicted": 1035643.65,
    "ai_insight": "Sales show a decreasing trend. Consider promotional activities...",
    "ollama_available": true
  }
}
```

**UI Recommendations:**
- Display as a line/area chart with confidence bands
- Show predicted total prominently
- Display AI insight in a card/banner
- Use color coding for confidence levels (green >70%, yellow 50-70%, red <50%)

---

### 2. Reorder Suggestions

Returns items that need restocking based on predicted demand.

**Endpoint:** `GET /forecast/reorder-suggestions`

**Query Parameters:**
| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `store_id` | integer | No | null | Filter by specific store |
| `urgency` | string | No | null | Filter by urgency: `critical`, `high`, `medium`, `low` |
| `refresh` | boolean | No | false | Force regenerate suggestions |

**Response:**
```json
{
  "success": true,
  "data": {
    "suggestions": [
      {
        "id": 1,
        "item": {
          "id": 42,
          "name": "YUMMY 7TONNER 50KG",
          "barcode": "123456789"
        },
        "store": {
          "id": 1,
          "name": "Main Store"
        },
        "current_stock": 0.00,
        "predicted_demand": 140.00,
        "suggested_quantity": 340.00,
        "days_until_stockout": 0,
        "urgency": "critical",
        "ai_reason": "Stock is depleted with high demand. Immediate reorder required to prevent lost sales."
      }
    ],
    "summary": {
      "critical": 8,
      "high": 3,
      "medium": 5,
      "low": 2
    },
    "ollama_available": true
  }
}
```

**Urgency Levels:**
| Level | Days Until Stockout | Color |
|-------|---------------------|-------|
| `critical` | 0-3 days | Red (#F1416C) |
| `high` | 4-7 days | Orange (#FFC700) |
| `medium` | 8-14 days | Blue (#009EF7) |
| `low` | 15-21 days | Gray (#7E8299) |

**UI Recommendations:**
- Show summary badges at top (Critical: 8, High: 3, etc.)
- List items sorted by urgency
- Use color-coded badges/pills for urgency
- Show AI reason in expandable section
- Add "Acknowledge" action button

---

### 3. Acknowledge Reorder Suggestion

Marks a reorder suggestion as acknowledged (user has taken action).

**Endpoint:** `POST /forecast/reorder-suggestions/{id}/acknowledge`

**Path Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | integer | Suggestion ID |

**Response:**
```json
{
  "success": true,
  "message": "Suggestion acknowledged"
}
```

**UI Recommendations:**
- Remove item from list or show as "Acknowledged"
- Update summary counts
- Optionally link to Purchase Order creation

---

### 4. Sales Patterns Analysis

Analyzes historical sales patterns by day of week.

**Endpoint:** `GET /forecast/patterns`

**Query Parameters:**
| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `days` | integer | No | 30 | Historical days to analyze (7-90) |
| `store_id` | integer | No | null | Filter by specific store |

**Response:**
```json
{
  "success": true,
  "data": {
    "trend": "decreasing",
    "average_daily_sales": 142786.52,
    "peak_day": {
      "date": "2026-01-20",
      "total": 256922.83,
      "transactions": 390
    },
    "lowest_day": {
      "date": "2026-01-28",
      "total": 76419.88,
      "transactions": 201
    },
    "day_of_week": [
      {
        "day": "Sunday",
        "day_number": 1,
        "avg_sales": 199783.02,
        "avg_transactions": 285.5,
        "sample_count": 4
      },
      {
        "day": "Monday",
        "day_number": 2,
        "avg_sales": 125639.78,
        "avg_transactions": 256.3,
        "sample_count": 4
      }
    ],
    "ai_insight": "Sundays and Saturdays show highest sales. Consider staffing adjustments...",
    "ollama_available": true
  }
}
```

**Trend Values:**
- `increasing` - Sales trending up >10%
- `decreasing` - Sales trending down >10%
- `stable` - Sales within ±10%
- `insufficient_data` - Less than 7 days of data

**UI Recommendations:**
- Display day_of_week as bar chart
- Show trend with arrow icon (↑ increasing, ↓ decreasing, → stable)
- Highlight peak and lowest days
- Display AI insight in a card

---

### 5. Item-Level Demand Forecast

Predicts demand for a specific item.

**Endpoint:** `GET /forecast/items/{itemId}/demand`

**Path Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `itemId` | integer | Item ID |

**Query Parameters:**
| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `days` | integer | No | 7 | Forecast period (1-30) |
| `store_id` | integer | No | null | Filter by specific store |

**Response (Success):**
```json
{
  "success": true,
  "data": {
    "item_id": 42,
    "forecast_date": "2026-02-08",
    "days_ahead": 7,
    "predicted_quantity": 140.00,
    "confidence": 75.31,
    "lower_bound": 98.00,
    "upper_bound": 182.00,
    "factors": {
      "avg_daily_sales": 20.00,
      "days_ahead": 7,
      "data_points": 30
    }
  }
}
```

**Response (No Data):**
```json
{
  "success": false,
  "message": "Insufficient sales data for this item"
}
```

**UI Recommendations:**
- Show on item detail page
- Display as gauge or simple stat card
- Show confidence level with color coding

---

### 6. Ollama Status

Checks if the AI service is available.

**Endpoint:** `GET /forecast/ollama-status`

**Response:**
```json
{
  "success": true,
  "data": {
    "available": true,
    "url": "http://192.168.0.6:11434",
    "configured_model": "qwen3:8b",
    "available_models": ["qwen3:8b", "llama3:8b"]
  }
}
```

**UI Recommendations:**
- Show status indicator (green dot = available, gray = unavailable)
- If unavailable, show message: "AI insights unavailable"
- Don't block functionality - forecasts work without AI

---

## Data Models

### Forecast
```typescript
interface Forecast {
  date: string;           // YYYY-MM-DD
  day: string;            // Day name (Monday, Tuesday, etc.)
  predicted_sales: number;
  confidence: number;     // 0-100
  lower_bound: number;
  upper_bound: number;
  factors: {
    day_of_week: string;
    based_on_samples: number;
    overall_avg: number;
  };
}
```

### ReorderSuggestion
```typescript
interface ReorderSuggestion {
  id: number;
  item: {
    id: number;
    name: string;
    barcode: string;
  } | null;
  store: {
    id: number;
    name: string;
  } | null;
  current_stock: number;
  predicted_demand: number;
  suggested_quantity: number;
  days_until_stockout: number;
  urgency: 'critical' | 'high' | 'medium' | 'low';
  ai_reason: string | null;
}
```

### DayOfWeekPattern
```typescript
interface DayOfWeekPattern {
  day: string;
  day_number: number;     // 1=Sunday, 7=Saturday
  avg_sales: number;
  avg_transactions: number;
  sample_count: number;
}
```

---

## Error Handling

All endpoints return errors in this format:

```json
{
  "success": false,
  "message": "Error description",
  "errors": {
    "field_name": ["Validation error message"]
  }
}
```

**HTTP Status Codes:**
- `200` - Success
- `401` - Unauthorized (invalid/missing token)
- `404` - Resource not found
- `422` - Validation error
- `500` - Server error

---

## UI/UX Guidelines

### Dashboard Layout
```
┌─────────────────────────────────────────────────┐
│  AI Status: ● Connected                         │
├─────────────────────────────────────────────────┤
│  ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌────────┐│
│  │ ₱1.03M  │ │  71.6%  │ │    8    │ │   18   ││
│  │Predicted│ │Confidence│ │Critical │ │ Total  ││
│  └─────────┘ └─────────┘ └─────────┘ └────────┘│
├─────────────────────────────────────────────────┤
│  AI Insight                                     │
│  ┌─────────────────────────────────────────────┐│
│  │ Sales show decreasing trend. Consider...    ││
│  └─────────────────────────────────────────────┘│
├─────────────────────────────────────────────────┤
│  Sales Forecast Chart                           │
│  ┌─────────────────────────────────────────────┐│
│  │     ╱╲                                      ││
│  │   ╱    ╲    ╱╲                              ││
│  │ ╱        ╲╱    ╲                            ││
│  │ Mon Tue Wed Thu Fri Sat Sun                 ││
│  └─────────────────────────────────────────────┘│
├─────────────────────────────────────────────────┤
│  Reorder Alerts                                 │
│  ┌─────────────────────────────────────────────┐│
│  │ 🔴 YUMMY 7TONNER 50KG        Stock: 0      ││
│  │    Suggested: 340 units      [Acknowledge] ││
│  ├─────────────────────────────────────────────┤│
│  │ 🟠 WINSTON RED 20'S          Stock: 5      ││
│  │    Suggested: 95 units       [Acknowledge] ││
│  └─────────────────────────────────────────────┘│
└─────────────────────────────────────────────────┘
```

### Color Palette
```
Primary:    #009EF7 (Blue)
Success:    #50CD89 (Green)
Warning:    #FFC700 (Orange)
Danger:     #F1416C (Red)
Info:       #7239EA (Purple)
Gray:       #7E8299
```

### Refresh Behavior
- Auto-refresh: Every 5 minutes
- Manual refresh: Pull-to-refresh
- Show loading skeleton while fetching
- Cache responses for 5 minutes

---

## Implementation Notes

1. **Caching**: Forecasts are cached server-side. Use `refresh=true` sparingly.

2. **AI Availability**: The system works without AI. When Ollama is unavailable:
   - `ai_insight` will be `null`
   - `ai_reason` will be `null`
   - All other data remains available

3. **Store Filtering**: If `store_id` is not provided, data is aggregated across all stores.

4. **Confidence Interpretation**:
   - High confidence (>70%): Prediction based on consistent historical patterns
   - Medium (50-70%): Some variability in historical data
   - Low (<50%): Limited data or high variability

5. **Suggested Quantity Formula**:
   ```
   suggested_qty = (avg_daily_sales × 14 days) - current_stock + (avg_daily_sales × 3 safety_days)
   ```
