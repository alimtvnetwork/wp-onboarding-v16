# GraphQL Conventions

> Version: 1.0.0 | Last Updated: 2025-01-26

## Overview

This document establishes GraphQL API design conventions for consistent, performant, and maintainable graph APIs across PHP, TypeScript, and Python implementations.

## Schema Design

### Naming Conventions

```graphql
# Types: PascalCase
type User {
  id: ID!
  email: String!
  createdAt: DateTime!
}

# Fields: camelCase
type Post {
  id: ID!
  title: String!
  publishedAt: DateTime
  authorId: ID!
  author: User!
}

# Enums: SCREAMING_SNAKE_CASE values
enum PostStatus {
  DRAFT
  PUBLISHED
  ARCHIVED
}

# Inputs: PascalCase with Input suffix
input CreatePostInput {
  title: String!
  content: String!
  status: PostStatus = DRAFT
}

# Connections: PascalCase with Connection suffix
type PostConnection {
  edges: [PostEdge!]!
  pageInfo: PageInfo!
  totalCount: Int!
}
```

### Type Definitions

```graphql
# Interfaces for shared fields
interface Node {
  id: ID!
}

interface Timestamped {
  createdAt: DateTime!
  updatedAt: DateTime!
}

# Union types for polymorphic returns
union SearchResult = User | Post | Comment

# Custom scalars
scalar DateTime
scalar JSON
scalar Upload
```

## Query Patterns

### Connection-Based Pagination

```graphql
type Query {
  # Relay-style connections
  users(
    first: Int
    after: String
    last: Int
    before: String
    filter: UserFilter
    orderBy: UserOrderBy
  ): UserConnection!
  
  # Single entity lookup
  user(id: ID!): User
  userByEmail(email: String!): User
}

type UserConnection {
  edges: [UserEdge!]!
  pageInfo: PageInfo!
  totalCount: Int!
}

type UserEdge {
  node: User!
  cursor: String!
}

type PageInfo {
  hasNextPage: Boolean!
  hasPreviousPage: Boolean!
  startCursor: String
  endCursor: String
}
```

### Filtering and Sorting

```graphql
input UserFilter {
  status: UserStatus
  createdAfter: DateTime
  createdBefore: DateTime
  search: String
  AND: [UserFilter!]
  OR: [UserFilter!]
  NOT: UserFilter
}

input UserOrderBy {
  field: UserOrderField!
  direction: OrderDirection!
}

enum UserOrderField {
  CREATED_AT
  UPDATED_AT
  EMAIL
  NAME
}

enum OrderDirection {
  ASC
  DESC
}
```

## Mutation Patterns

### Input/Payload Pattern

```graphql
type Mutation {
  createUser(input: CreateUserInput!): CreateUserPayload!
  updateUser(input: UpdateUserInput!): UpdateUserPayload!
  deleteUser(input: DeleteUserInput!): DeleteUserPayload!
}

input CreateUserInput {
  email: String!
  name: String!
  role: UserRole = MEMBER
}

type CreateUserPayload {
  user: User
  errors: [UserError!]!
}

type UserError {
  field: String
  message: String!
  code: ErrorCode!
}

enum ErrorCode {
  VALIDATION_ERROR
  NOT_FOUND
  UNAUTHORIZED
  CONFLICT
  INTERNAL_ERROR
}
```

### TypeScript Resolver Implementation

```typescript
// src/graphql/resolvers/user.resolver.ts
import { GraphQLError } from 'graphql';
import { Context } from '../context';
import { CreateUserInput, UpdateUserInput } from '../generated/types';

export const userResolvers = {
  Query: {
    user: async (
      _parent: unknown,
      args: { id: string },
      context: Context
    ) => {
      const user = await context.dataSources.users.findById(args.id);
      
      if (!user) {
        return null;
      }
      
      return user;
    },
    
    users: async (
      _parent: unknown,
      args: ConnectionArgs<UserFilter, UserOrderBy>,
      context: Context
    ) => {
      return context.dataSources.users.findMany({
        first: args.first,
        after: args.after,
        filter: args.filter,
        orderBy: args.orderBy,
      });
    },
  },
  
  Mutation: {
    createUser: async (
      _parent: unknown,
      args: { input: CreateUserInput },
      context: Context
    ) => {
      // Validate input
      const validationErrors = validateCreateUserInput(args.input);
      if (validationErrors.length > 0) {
        return { user: null, errors: validationErrors };
      }
      
      try {
        const user = await context.dataSources.users.create(args.input);
        return { user, errors: [] };
      } catch (error) {
        if (error instanceof ConflictError) {
          return {
            user: null,
            errors: [{
              field: 'email',
              message: 'Email already exists',
              code: 'CONFLICT',
            }],
          };
        }
        throw error;
      }
    },
  },
  
  User: {
    // Field resolver for related data
    posts: async (
      parent: User,
      args: ConnectionArgs,
      context: Context
    ) => {
      return context.dataSources.posts.findByAuthorId(parent.id, args);
    },
  },
};
```

## DataLoader Pattern

### Batching and Caching

```typescript
// src/graphql/dataloaders/user.loader.ts
import DataLoader from 'dataloader';
import { UserRepository } from '../../repositories/user.repository';

export const createUserLoader = (userRepository: UserRepository) => {
  return new DataLoader<string, User | null>(
    async (ids: readonly string[]) => {
      const users = await userRepository.findByIds([...ids]);
      
      // Map results to maintain order
      const userMap = new Map(users.map(u => [u.id, u]));
      return ids.map(id => userMap.get(id) ?? null);
    },
    {
      // Cache within single request
      cache: true,
      // Batch within 10ms window
      batchScheduleFn: callback => setTimeout(callback, 10),
    }
  );
};

// Context creation
export const createContext = (req: Request): Context => {
  const userRepository = new UserRepository();
  
  return {
    req,
    dataSources: {
      users: userRepository,
    },
    loaders: {
      user: createUserLoader(userRepository),
      post: createPostLoader(new PostRepository()),
    },
  };
};

// Usage in resolver
const resolvers = {
  Post: {
    author: async (parent: Post, _args: unknown, context: Context) => {
      // Uses batching - multiple posts will batch author lookups
      return context.loaders.user.load(parent.authorId);
    },
  },
};
```

## Error Handling

### Error Types

```typescript
// src/graphql/errors.ts
import { GraphQLError } from 'graphql';

export class NotFoundError extends GraphQLError {
  constructor(resource: string, id: string) {
    super(`${resource} with ID ${id} not found`, {
      extensions: {
        code: 'NOT_FOUND',
        resource,
        id,
      },
    });
  }
}

export class ValidationError extends GraphQLError {
  constructor(message: string, field?: string) {
    super(message, {
      extensions: {
        code: 'VALIDATION_ERROR',
        field,
      },
    });
  }
}

export class AuthorizationError extends GraphQLError {
  constructor(action: string, resource: string) {
    super(`Not authorized to ${action} ${resource}`, {
      extensions: {
        code: 'FORBIDDEN',
        action,
        resource,
      },
    });
  }
}

// Error formatter
export const formatError = (error: GraphQLError) => {
  // Log internal errors
  if (!error.extensions?.code) {
    console.error('Unhandled GraphQL error:', error);
    
    return new GraphQLError('Internal server error', {
      extensions: { code: 'INTERNAL_ERROR' },
    });
  }
  
  return error;
};
```

## Subscriptions

### Real-time Updates

```graphql
type Subscription {
  postCreated(authorId: ID): Post!
  postUpdated(id: ID!): Post!
  notificationReceived(userId: ID!): Notification!
}
```

```typescript
// src/graphql/resolvers/subscription.resolver.ts
import { PubSub, withFilter } from 'graphql-subscriptions';

const pubsub = new PubSub();

export const EVENTS = {
  POST_CREATED: 'POST_CREATED',
  POST_UPDATED: 'POST_UPDATED',
  NOTIFICATION: 'NOTIFICATION',
} as const;

export const subscriptionResolvers = {
  Subscription: {
    postCreated: {
      subscribe: withFilter(
        () => pubsub.asyncIterator([EVENTS.POST_CREATED]),
        (payload, variables) => {
          // Filter by author if specified
          if (variables.authorId) {
            return payload.postCreated.authorId === variables.authorId;
          }
          return true;
        }
      ),
    },
    
    notificationReceived: {
      subscribe: withFilter(
        () => pubsub.asyncIterator([EVENTS.NOTIFICATION]),
        (payload, variables, context) => {
          // Only receive own notifications
          return payload.notification.userId === context.userId;
        }
      ),
    },
  },
};

// Publishing events
export const publishPostCreated = (post: Post) => {
  pubsub.publish(EVENTS.POST_CREATED, { postCreated: post });
};
```

## Security

### Authorization Directives

```graphql
directive @auth(
  requires: Role = USER
) on FIELD_DEFINITION | OBJECT

directive @owner on FIELD_DEFINITION

enum Role {
  ADMIN
  USER
  GUEST
}

type Query {
  me: User @auth
  adminDashboard: Dashboard @auth(requires: ADMIN)
}

type User {
  id: ID!
  email: String! @owner
  publicName: String!
}
```

```typescript
// src/graphql/directives/auth.directive.ts
import { mapSchema, getDirective, MapperKind } from '@graphql-tools/utils';
import { defaultFieldResolver, GraphQLSchema } from 'graphql';

export const authDirectiveTransformer = (schema: GraphQLSchema) => {
  return mapSchema(schema, {
    [MapperKind.OBJECT_FIELD]: (fieldConfig) => {
      const authDirective = getDirective(schema, fieldConfig, 'auth')?.[0];
      
      if (authDirective) {
        const { requires } = authDirective;
        const originalResolver = fieldConfig.resolve ?? defaultFieldResolver;
        
        fieldConfig.resolve = async (source, args, context, info) => {
          if (!context.user) {
            throw new AuthenticationError('Must be logged in');
          }
          
          if (requires && !hasRole(context.user, requires)) {
            throw new AuthorizationError('view', info.fieldName);
          }
          
          return originalResolver(source, args, context, info);
        };
      }
      
      return fieldConfig;
    },
  });
};
```

### Query Complexity Limiting

```typescript
// src/graphql/complexity.ts
import { getComplexity, simpleEstimator, fieldExtensionsEstimator } from 'graphql-query-complexity';

const MAX_COMPLEXITY = 1000;

export const complexityPlugin = {
  requestDidStart: () => ({
    didResolveOperation({ request, document, schema }) {
      const complexity = getComplexity({
        schema,
        operationName: request.operationName,
        query: document,
        variables: request.variables,
        estimators: [
          fieldExtensionsEstimator(),
          simpleEstimator({ defaultComplexity: 1 }),
        ],
      });
      
      if (complexity > MAX_COMPLEXITY) {
        throw new GraphQLError(
          `Query complexity ${complexity} exceeds maximum ${MAX_COMPLEXITY}`
        );
      }
    },
  }),
};
```

## Testing

### Resolver Testing

```typescript
// src/graphql/__tests__/user.resolver.test.ts
import { createTestContext } from '../test-utils';
import { userResolvers } from '../resolvers/user.resolver';

describe('User Resolvers', () => {
  describe('Query.user', () => {
    it('should return user by ID', async () => {
      // Arrange
      const context = createTestContext({
        users: [{ id: '1', email: 'test@example.com', name: 'Test' }],
      });
      
      // Act
      const result = await userResolvers.Query.user(
        {},
        { id: '1' },
        context
      );
      
      // Assert
      expect(result).toEqual({
        id: '1',
        email: 'test@example.com',
        name: 'Test',
      });
    });
    
    it('should return null for non-existent user', async () => {
      const context = createTestContext({ users: [] });
      
      const result = await userResolvers.Query.user(
        {},
        { id: 'nonexistent' },
        context
      );
      
      expect(result).toBeNull();
    });
  });
  
  describe('Mutation.createUser', () => {
    it('should create user with valid input', async () => {
      const context = createTestContext();
      
      const result = await userResolvers.Mutation.createUser(
        {},
        { input: { email: 'new@example.com', name: 'New User' } },
        context
      );
      
      expect(result.errors).toHaveLength(0);
      expect(result.user).toMatchObject({
        email: 'new@example.com',
        name: 'New User',
      });
    });
  });
});
```

## Performance

### Query Optimization

```typescript
// Field-level caching
const resolvers = {
  Query: {
    expensiveQuery: {
      resolve: async (_parent, args, context) => {
        const cacheKey = `expensive:${JSON.stringify(args)}`;
        
        const cached = await context.cache.get(cacheKey);
        if (cached) {
          return JSON.parse(cached);
        }
        
        const result = await computeExpensiveResult(args);
        await context.cache.set(cacheKey, JSON.stringify(result), 'EX', 300);
        
        return result;
      },
    },
  },
};

// Automatic persisted queries
import { ApolloServer } from '@apollo/server';
import { ApolloServerPluginCacheControl } from '@apollo/server/plugin/cacheControl';

const server = new ApolloServer({
  typeDefs,
  resolvers,
  plugins: [
    ApolloServerPluginCacheControl({
      defaultMaxAge: 60,
    }),
  ],
  persistedQueries: {
    cache: new KeyValueCache(),
  },
});
```

## Code Generation

### Schema-First Development

```yaml
# codegen.yml
schema: src/graphql/schema/**/*.graphql
documents: src/**/*.graphql
generates:
  src/graphql/generated/types.ts:
    plugins:
      - typescript
      - typescript-resolvers
    config:
      contextType: ../context#Context
      mappers:
        User: ../../models/user#UserModel
        Post: ../../models/post#PostModel
      scalars:
        DateTime: Date
        JSON: Record<string, unknown>
```

## Summary

| Aspect | Convention |
|--------|-----------|
| Types | PascalCase |
| Fields | camelCase |
| Enums | SCREAMING_SNAKE_CASE |
| Inputs | PascalCase + Input suffix |
| Pagination | Relay-style connections |
| Errors | Typed payloads with error arrays |
| Auth | Directive-based |
| Batching | DataLoader pattern |
