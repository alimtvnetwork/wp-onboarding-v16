// Package ws — WebSocket client connection handling.
package ws

import (
	"encoding/json"
	"time"

	connectionstatus "wp-plugin-publish/internal/enums/connectionstatustype"

	"github.com/gorilla/websocket"
	"net/http"
)

// HandleWebSocket handles WebSocket upgrade requests
func (h *Hub) HandleWebSocket(w http.ResponseWriter, r *http.Request) {
	conn, err := upgrader.Upgrade(w, r, nil)
	if err != nil {
		return
	}

	client := &Client{
		hub:  h,
		conn: conn,
		send: make(chan []byte, 256),
	}

	h.register <- client

	// Send connection confirmation
	Broadcast(h, EventConnection, ConnectionConfirmation{
		Status:   connectionstatus.Connected.DBValue(),
		ClientId: conn.RemoteAddr().String(),
	})

	// Start goroutines for reading and writing
	go client.writePump()
	go client.readPump()
}

// readPump pumps messages from the WebSocket connection to the hub
func (c *Client) readPump() {
	defer func() {
		c.hub.unregister <- c
		c.conn.Close()
	}()

	c.conn.SetReadDeadline(time.Now().Add(60 * time.Second))
	c.conn.SetPongHandler(func(string) error {
		c.conn.SetReadDeadline(time.Now().Add(60 * time.Second))
		return nil
	})

	for {
		_, message, err := c.conn.ReadMessage()
		if err != nil {
			break
		}

		// Handle incoming messages (e.g., subscription requests)
		c.handleMessage(message)
	}
}

// handleMessage processes incoming WebSocket messages
func (c *Client) handleMessage(message []byte) {
	var msg IncomingMessage

	err := json.Unmarshal(message, &msg)

	if err != nil {
		return
	}

	switch msg.Type {
	case "subscribe_plugin":
		// Handle plugin subscription
	case "unsubscribe_plugin":
		// Handle plugin unsubscription
	case "ping":
		// Respond to ping
		c.send <- []byte(`{"type":"pong"}`)
	}
}

// writePump pumps messages from the hub to the WebSocket connection
func (c *Client) writePump() {
	ticker := time.NewTicker(30 * time.Second)
	defer func() {
		ticker.Stop()
		c.conn.Close()
	}()

	for {
		select {
		case message, ok := <-c.send:
			c.conn.SetWriteDeadline(time.Now().Add(10 * time.Second))
			if !ok {
				c.conn.WriteMessage(websocket.CloseMessage, []byte{})
				return
			}

			writeErr := c.conn.WriteMessage(websocket.TextMessage, message)

			if writeErr != nil {
				return
			}

		case <-ticker.C:
			c.conn.SetWriteDeadline(time.Now().Add(10 * time.Second))
			pingErr := c.conn.WriteMessage(websocket.PingMessage, nil)

			if pingErr != nil {
				return
			}
		}
	}
}

// ClientCount returns the number of connected clients
func (h *Hub) ClientCount() int {
	h.mu.RLock()
	defer h.mu.RUnlock()
	return len(h.clients)
}
